<?php

/**
 * Vite manifest parser.
 *
 * Reads a Vite-generated manifest.json and resolves entrypoints,
 * imports and stylesheets with their hashed URLs.
 *
 * Copyright 2022 Jan T. Sott
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * Original file: https://github.com/idleberg/php-vite-manifest
 *
 * @package Buttonizer\Core\Utils
 *
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Utils;

class ManifestParser
{
    private $manifest;
    private $baseUri;

    public function __construct(string $manifestFile, string $baseUri)
    {
        if (!file_exists(realpath($manifestFile))) {
            throw new \Exception("Manifest file does not exist: $manifestFile");
        }

        try {
            $this->manifest = json_decode(
                file_get_contents($manifestFile),
                true
            );
        } catch (\Throwable $errorMessage) {
            throw new \Exception("Failed loading manifest: $errorMessage");
        }

        if (!parse_url($baseUri)) {
            throw new \Exception("Failed to parse URL: $baseUri");
        }

        $this->baseUri = $baseUri;
    }

    /**
     * Returns the contents of the manifest file
     *
     * @return array
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * Returns the entrypoint from the manifest
     *
     * @param string $entrypoint
     * @param bool $hash
     * @return array
     */
    public function getEntrypoint(string $entrypoint, bool $hash = true): array
    {
        return isset($this->manifest[$entrypoint]) ? [
            "hash" => $hash ? $this->getFileHash($this->manifest[$entrypoint]["file"]) : null,
            "url"  => $this->getPath($this->manifest[$entrypoint]["file"])
        ] : [];
    }

    /**
     * Returns imports for a file listed in the manifest
     *
     * @param string $entrypoint
     * @param bool $hash
     * @return array
     */
    public function getImports(string $entrypoint, bool $hash = true): array
    {
        if (!isset($this->manifest[$entrypoint]["imports"])) {
            return [];
        }

        return array_filter(
            array_map(function ($import) use ($hash) {
                return isset($this->manifest[$import]["file"]) ? [
                    "hash" => $hash ? $this->getFileHash($this->manifest[$import]["file"]) : null,
                    "url"  => $this->getPath($this->manifest[$import]["file"])
                ] : [];
            }, $this->manifest[$entrypoint]["imports"])
        );
    }

    /**
     * Returns stylesheets for a file listed in the manifest
     *
     * @param string $entrypoint
     * @param bool $hash
     * @return array
     */
    public function getStyles(string $entrypoint, bool $hash = true): array
    {
        $styles = [];

        // CSS from the entrypoint itself
        if (isset($this->manifest[$entrypoint]["css"])) {
            foreach ($this->manifest[$entrypoint]["css"] as $style) {
                $styles[] = [
                    "hash" => $hash ? $this->getFileHash($style) : null,
                    "url"  => $this->getPath($style)
                ];
            }
        }

        // CSS from imported chunks (e.g. vendor)
        if (isset($this->manifest[$entrypoint]["imports"])) {
            foreach ($this->manifest[$entrypoint]["imports"] as $import) {
                if (isset($this->manifest[$import]["css"])) {
                    foreach ($this->manifest[$import]["css"] as $style) {
                        $styles[] = [
                            "hash" => $hash ? $this->getFileHash($style) : null,
                            "url"  => $this->getPath($style)
                        ];
                    }
                }
            }
        }

        return $styles;
    }

    /**
     * Returns SHA-256 hash of file
     *
     * @param string $file
     * @return string
     */
    private function getFileHash(string $file): string
    {
        return "sha256-" . base64_encode(
            openssl_digest(
                file_get_contents(
                    $this->getPath($file)
                ),
                "sha256",
                true
            )
        );
    }

    /**
     * Resolves URL for a given file path
     *
     * @param string $relativePath
     * @return string
     */
    private function getPath(string $relativePath): string
    {
        return $this->baseUri . "/" . $relativePath;
    }
}

