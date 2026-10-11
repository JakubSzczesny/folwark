<?php

declare(strict_types=1);

namespace App\Storage;

use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Miniatury zdjęć z magazynu plików. Zdjęcia nie leżą w katalogu publicznym, więc miniatury
 * wydaje kontroler po sprawdzeniu uprawnień; gotowe trzymamy obok oryginału w katalogu „.miniatury”.
 */
class Thumbnails
{
    public const array FILTERS = ['thumb', 'card', 'photo'];

    public function __construct(
        private readonly FileStorage $storage,
        private readonly DataManager $dataManager,
        private readonly FilterManager $filterManager,
    ) {
    }

    public function response(string $path, string $filter): Response
    {
        if (!\in_array($filter, self::FILTERS, true)) {
            throw new \InvalidArgumentException(\sprintf('Nieznany rozmiar miniatury „%s”.', $filter));
        }

        $cached = self::cachePath($path, $filter);
        if (!$this->storage->exists($cached)) {
            $binary = $this->filterManager->applyFilter($this->dataManager->find($filter, $path), $filter);
            $content = $binary->getContent();
            if (false === $content) {
                throw new \RuntimeException(\sprintf('Nie udało się przygotować miniatury „%s”.', $path));
            }
            $this->storage->write($cached, $content);
        }

        $response = $this->storage->response($cached, basename($path), true);
        $response->setPrivate();
        $response->setMaxAge(7 * 86400);

        return $response;
    }

    /**
     * Usuwa plik razem z jego miniaturami.
     */
    public function delete(string $path): void
    {
        $this->storage->delete($path);
        foreach (self::FILTERS as $filter) {
            $this->storage->delete(self::cachePath($path, $filter));
        }
    }

    public static function isImage(?string $mimeType): bool
    {
        return \in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    private static function cachePath(string $path, string $filter): string
    {
        return \dirname($path).'/.miniatury/'.$filter.'/'.basename($path);
    }
}
