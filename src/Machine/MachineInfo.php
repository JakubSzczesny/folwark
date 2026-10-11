<?php

declare(strict_types=1);

namespace App\Machine;

use App\Entity\Machine;
use App\Entity\MachineInfoAttachment;
use App\Entity\MachineInfoCategory;
use App\Entity\MachineInfoEntry;
use App\Enum\AttachmentType;
use App\Repository\MachineInfoCategoryRepository;
use App\Repository\MachineInfoEntryRepository;
use App\Storage\FileStorage;
use App\Storage\Thumbnails;
use App\Tree\CategoryTree;
use App\Tree\TreeNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Informacje o maszynie: drzewo kategorii, wpisy z załącznikami, kopiowanie między maszynami.
 */
final class MachineInfo
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MachineInfoCategoryRepository $categories,
        private readonly MachineInfoEntryRepository $entries,
        private readonly FileStorage $storage,
        private readonly Thumbnails $thumbnails,
    ) {
    }

    /**
     * Drzewo kategorii z liczbą wpisów maszyny (puste ukryte, chyba że $showAll).
     *
     * @return array{nodes: list<TreeNode<MachineInfoCategory>>, selected: ?TreeNode<MachineInfoCategory>, hidden: int}
     */
    public function tree(Machine $machine, ?int $selectedId = null, bool $showAll = false): array
    {
        return CategoryTree::build($this->categories->findAllOrdered(), $this->entries->countByCategory($machine), $selectedId, $showAll);
    }

    /**
     * Wpisy wybranej kategorii i jej podkategorii, pogrupowane w kolejności drzewa.
     *
     * @param TreeNode<MachineInfoCategory> $selected
     *
     * @return list<array{category: MachineInfoCategory, entries: list<MachineInfoEntry>}>
     */
    public function sections(Machine $machine, TreeNode $selected): array
    {
        $entries = [];
        foreach ($this->entries->findForMachine($machine, $selected->ids()) as $entry) {
            $entries[$entry->getCategory()->getId()][] = $entry;
        }

        $sections = [];
        foreach ($selected->flatten() as $node) {
            if (isset($entries[$node->id()]) || $node === $selected) {
                $sections[] = ['category' => $node->category, 'entries' => $entries[$node->id()] ?? []];
            }
        }

        return $sections;
    }

    public function addCategory(string $name, ?MachineInfoCategory $parent): MachineInfoCategory
    {
        $category = new MachineInfoCategory(trim($name), $parent, $this->categories->nextPosition($parent));
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * Usunąć można tylko kategorię bez wpisów (w żadnej maszynie) i bez podkategorii.
     */
    public function deleteCategory(MachineInfoCategory $category): bool
    {
        if ($this->categories->isUsed($category)) {
            return false;
        }

        $this->em->remove($category);
        $this->em->flush();

        return true;
    }

    /**
     * Nowa kolejność rodzeństwa po przeciągnięciu. Identyfikatory spoza tego rodzica są pomijane.
     *
     * @param list<int> $ids
     */
    public function reorder(?MachineInfoCategory $parent, array $ids): void
    {
        CategoryTree::reorder($this->categories->findBy(['parent' => $parent]), $ids);
        $this->em->flush();
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function saveEntry(MachineInfoEntry $entry, array $files = [], string $links = ''): void
    {
        $this->em->persist($entry);
        $this->em->flush();

        foreach ($files as $file) {
            $type = Thumbnails::isImage($file->getMimeType()) ? AttachmentType::Photo : AttachmentType::File;
            $path = $this->storage->storeUpload($file, MachineManager::directory($entry->getMachine()).'/info');
            $entry->addAttachment(new MachineInfoAttachment($entry, $type, $path, mb_substr($file->getClientOriginalName(), 0, 255)));
        }
        foreach (self::parseLinks($links) as [$url, $description]) {
            $entry->addAttachment(new MachineInfoAttachment($entry, AttachmentType::Link, $url, $description));
        }

        $this->em->flush();
    }

    public function deleteEntry(MachineInfoEntry $entry): void
    {
        foreach ($entry->getAttachments() as $attachment) {
            $this->deleteFile($attachment);
        }
        $this->em->remove($entry);
        $this->em->flush();
    }

    public function deleteAttachment(MachineInfoAttachment $attachment): void
    {
        $this->deleteFile($attachment);
        $attachment->getEntry()->removeAttachment($attachment);
        $this->em->flush();
    }

    /**
     * Kopiuje wpisy wybranych kategorii (z podkategoriami) z innej maszyny, razem z załącznikami.
     * Wpis o tej samej nazwie i numerze katalogowym w tej samej kategorii jest pomijany.
     *
     * @param list<MachineInfoCategory> $categories
     *
     * @return int liczba skopiowanych wpisów
     */
    public function copy(Machine $from, Machine $to, array $categories): int
    {
        $ids = [];
        foreach ($categories as $category) {
            array_push($ids, ...$this->descendantIds($category));
        }

        $existing = [];
        foreach ($this->entries->findForMachine($to, array_values(array_unique($ids))) as $entry) {
            $existing[self::entryKey($entry)] = true;
        }

        $copied = 0;
        foreach ($this->entries->findForMachine($from, array_values(array_unique($ids))) as $source) {
            if (isset($existing[self::entryKey($source)])) {
                continue;
            }

            $entry = (new MachineInfoEntry($to, $source->getCategory()))
                ->setName($source->getName())
                ->setCatalogNumber($source->getCatalogNumber())
                ->setManufacturer($source->getManufacturer())
                ->setSizeQuantity($source->getSizeQuantity())
                ->setNotes($source->getNotes());
            $this->em->persist($entry);

            foreach ($source->getAttachments() as $attachment) {
                $location = $attachment->isLink()
                    ? $attachment->getLocation()
                    : $this->storage->copy($attachment->getLocation(), MachineManager::directory($to).'/info');
                $entry->addAttachment(new MachineInfoAttachment($entry, $attachment->getType(), $location, $attachment->getDescription()));
            }
            ++$copied;
        }

        $this->em->flush();

        return $copied;
    }

    /**
     * Każda linia to jeden link; tekst przed adresem staje się opisem, np. „Sklep Agrihandel https://…”.
     *
     * @return list<array{0: string, 1: ?string}>
     */
    public static function parseLinks(string $text): array
    {
        $links = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (!preg_match('~(https?://\S+)~iu', $line, $match, \PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $url = mb_substr(rtrim($match[1][0], '.,;)'), 0, 1024);
            $description = trim(substr($line, 0, $match[1][1]), " \t-–—:|");
            $links[] = [$url, '' === $description ? null : mb_substr($description, 0, 255)];
        }

        return $links;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(MachineInfoCategory $category): array
    {
        $ids = [(int) $category->getId()];
        foreach ($category->getChildren() as $child) {
            array_push($ids, ...$this->descendantIds($child));
        }

        return $ids;
    }

    private static function entryKey(MachineInfoEntry $entry): string
    {
        return $entry->getCategory()->getId().'|'.mb_strtolower(trim($entry->getName())).'|'.mb_strtolower(trim((string) $entry->getCatalogNumber()));
    }

    private function deleteFile(MachineInfoAttachment $attachment): void
    {
        if (!$attachment->isLink()) {
            $this->thumbnails->delete($attachment->getLocation());
        }
    }
}
