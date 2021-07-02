<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace Rector\OxidEsales\Update;

use Rector\Renaming\ValueObject\MethodCallRename;

final class MethodCallRenameGenerator
{
    /** @var string */
    private $temporaryFile;

    /**
     * Forms array with MethodCallRename for underscored methods if class name and old method from the input file
     * were found in module
     * @param string $inputFile
     * @param string $modulePath
     * @return array
     * @throws \ReflectionException
     */
    public function getMethodCallRenamesForModule(string $inputFile, string $modulePath): array
    {
        $methodCallRenames = [];
        $this->setTemporaryFile($inputFile);
        $this->mergeModuleContentsToTemporaryFile($modulePath);
        $classMethodArray = array_map('str_getcsv', file($inputFile));
        foreach ($classMethodArray as [$class, $oldMethod, $newMethod]) {
            $className = (new \ReflectionClass($class))->getShortName();
            if (!$this->temporaryFileContainsStrings($className, $oldMethod)) {
                continue;
            }
            $methodCallRenames[] = new MethodCallRename($class, $oldMethod, $newMethod);
        }
        $this->removeTemporaryFile();
        return $methodCallRenames;
    }

    private function setTemporaryFile(string $inputFile): void
    {
        $this->temporaryFile = dirname($inputFile) . '/merged-module-contents.tmp';
    }

    /** @param string $modulePath */
    private function mergeModuleContentsToTemporaryFile(string $modulePath): void
    {
        $moduleFilesIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulePath)
        );
        $modulePhpFilesIterator = new \RegexIterator(
            $moduleFilesIterator,
            '/\.php$/'
        );
        $output = fopen($this->temporaryFile, 'wb+');
        foreach ($modulePhpFilesIterator as $file) {
            fwrite($output, file_get_contents($file->getPathname()));
        }
        fclose($output);
    }

    /**
     * @param string $className
     * @param string $methodName
     * @return bool
     */
    private function temporaryFileContainsStrings(string $className, string $methodName): bool
    {
        $content = file_get_contents($this->temporaryFile);
        return strpos($content, $methodName) !== false
            && strpos($content, $className) !== false;
    }

    private function removeTemporaryFile(): void
    {
        unlink($this->temporaryFile);
    }
}
