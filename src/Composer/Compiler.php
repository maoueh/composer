<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The Compiler class compiles composer into a phar
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Compiler
{
    /**
     * Compiles composer into a single phar file
     *
     * @throws \RuntimeException
     * @param string $pharFile The full path to the file to create
     */
    public function compile($pharFile = 'composer.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $process = new Process('git log --pretty="%h" -n1 HEAD', __DIR__);
        if ($process->run() != 0) {
            throw new \RuntimeException('Can\'t run git log. You must ensure to run compile from composer git repository clone and that git binary is available.');
        }
        $this->version = trim($process->getOutput());

        $process = new Process('git describe --tags HEAD');
        if ($process->run() == 0) {
            $this->version = trim($process->getOutput());
        }

        $phar = new \Phar($pharFile, 0, 'composer.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->notName('ClassLoader.php')
            ->in(__DIR__.'/..')
        ;

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/Autoload/ClassLoader.php'), false);
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../res/composer-schema.json'), false);

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->in(__DIR__.'/../../vendor/symfony/')
            ->in(__DIR__.'/../../vendor/seld/jsonlint/src/')
            ->in(__DIR__.'/../../vendor/justinrainbow/json-schema/src/')
        ;

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/.composer/ClassLoader.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/.composer/autoload.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../vendor/.composer/autoload_namespaces.php'));
        $this->addComposerBin($phar);

        // Stubs
        $phar->setStub($this->getStub());

        $phar->stopBuffering();

        // disabled for interoperability with systems without gzip ext
        // $phar->compressFiles(\Phar::GZ);

        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../LICENSE'), false);

        unset($phar);
    }

    private function addFile($phar, $file, $strip = true)
    {
        $path = str_replace(dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR, '', $file->getRealPath());

        if ($strip) {
            $content = php_strip_whitespace($file);
        } elseif ('LICENSE' === basename($file)) {
            $content = "\n".file_get_contents($file)."\n";
        } else {
            $content = file_get_contents($file);
        }

        $content = str_replace('@package_version@', $this->version, $content);

        $phar->addFromString($path, $content);
    }

    private function addComposerBin($phar)
    {
        $content = file_get_contents(__DIR__.'/../../bin/composer');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/composer', $content);
    }

    private function getStub()
    {
        return <<<'EOF'
#!/usr/bin/env php
<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view
 * the license that is located at the bottom of this file.
 */

Phar::mapPhar('composer.phar');

require 'phar://composer.phar/bin/composer';

__HALT_COMPILER();
EOF;
    }
}
