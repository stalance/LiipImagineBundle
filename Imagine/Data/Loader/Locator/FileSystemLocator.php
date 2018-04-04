<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Imagine\Data\Loader\Locator;

use Liip\ImagineBundle\Exception\File\Loader\NotLoadableException;
use Liip\ImagineBundle\Exception\InvalidArgumentException;

class FileSystemLocator implements LocatorInterface
{
    /**
     * @var string[]
     */
    private $roots = [];

    /**
     * @param string[] $roots
     */
    public function __construct(array $roots = [])
    {
        $this->roots = array_map(function (string $root): string {
            return $this->sanitizeRootPath($root);
        }, $roots);
    }

    /**
     * @param string $path
     *
     * @throws NotLoadableException
     *
     * @return string
     */
    public function locate(string $path): string
    {
        if (null !== $absolute = $this->locateUsingRootPlaceholder($path)) {
            return $this->sanitizeAbsolutePath($absolute);
        }

        if (null !== $absolute = $this->locateUsingRootPathsSearch($path)) {
            return $this->sanitizeAbsolutePath($absolute);
        }

        throw new NotLoadableException(
            'Source image not resolvable "%s" in root path(s) "%s"', $path, implode(':', $this->roots)
        );
    }

    /**
     * @param string $root
     * @param string $path
     *
     * @return string|null
     */
    protected function generateAbsolutePath(string $root, string $path): ?string
    {
        if (false !== $absolute = realpath($root.DIRECTORY_SEPARATOR.$path)) {
            return $absolute;
        }

        return null;
    }

    /**
     * @param string $path
     *
     * @return string|null
     */
    private function locateUsingRootPathsSearch(string $path): ?string
    {
        foreach ($this->roots as $root) {
            if (null !== $absolute = $this->generateAbsolutePath($root, $path)) {
                return $absolute;
            }
        }

        return null;
    }

    /**
     * @param string $path
     *
     * @return string|null
     */
    private function locateUsingRootPlaceholder(string $path): ?string
    {
        if (0 !== mb_strpos($path, '@') || 1 !== preg_match('{^@(?<name>[^:]+):(?<path>.+)$}', $path, $match)) {
            return null;
        }

        if (isset($this->roots[$match['name']])) {
            return $this->generateAbsolutePath($this->roots[$match['name']], $match['path']);
        }

        throw new NotLoadableException(
            'Invalid root placeholder "@%s" for path "%s"', $match['name'], $match['path']
        );
    }

    /**
     * @param string $path
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    private function sanitizeRootPath(string $path): string
    {
        if (!empty($path) && false !== $real = realpath($path)) {
            return $real;
        }

        throw new InvalidArgumentException('Root image path not resolvable "%s"', $path);
    }

    /**
     * @param string $path
     *
     * @throws NotLoadableException
     *
     * @return string
     */
    private function sanitizeAbsolutePath(string $path): string
    {
        foreach ($this->roots as $root) {
            if (0 === mb_strpos($path, $root)) {
                return $path;
            }
        }

        throw new NotLoadableException(sprintf('Source image invalid "%s" as it is outside of the defined root path(s) "%s"',
            $path, implode(':', $this->roots)));
    }
}