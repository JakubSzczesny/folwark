<?php

declare(strict_types=1);

namespace App\Storage;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

/**
 * Pliki aplikacji (XML z KSeF, załączniki, zdjęcia) – lokalnie przez Flysystem, poza katalogiem public.
 */
class FileStorage
{
    public function __construct(
        #[Autowire(service: 'default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function write(string $path, string $contents): void
    {
        $this->storage->write($path, $contents);
    }

    public function read(string $path): string
    {
        return $this->storage->read($path);
    }

    public function exists(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    public function delete(string $path): void
    {
        if ($this->storage->fileExists($path)) {
            $this->storage->delete($path);
        }
    }

    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $name = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $safe = (new AsciiSlugger('pl'))->slug($name)->lower()->truncate(60)->toString() ?: 'plik';
        $path = trim($directory, '/').'/'.Uuid::v7()->toBase58().'-'.$safe.('' !== $extension ? '.'.$extension : '');

        $stream = fopen($file->getPathname(), 'r');
        if (false === $stream) {
            throw new \RuntimeException('Nie można odczytać przesłanego pliku.');
        }
        try {
            $this->storage->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return $path;
    }

    public function response(string $path, string $downloadName, bool $inline = false): StreamedResponse
    {
        $mime = $this->storage->mimeType($path);
        $storage = $this->storage;

        $response = new StreamedResponse(static function () use ($storage, $path): void {
            $stream = $storage->readStream($path);
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $downloadName,
            (new AsciiSlugger())->slug(pathinfo($downloadName, \PATHINFO_FILENAME))->toString().'.'.pathinfo($downloadName, \PATHINFO_EXTENSION),
        ));

        return $response;
    }
}
