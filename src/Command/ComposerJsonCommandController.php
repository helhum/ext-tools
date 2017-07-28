<?php
declare(strict_types=1);
namespace Helhum\ExtTools\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Helmut Hummel <info@helhum.io>
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the text file GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Semver\VersionParser;
use Helhum\Typo3Console\Mvc\Controller\CommandController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ComposerJsonCommandController extends CommandController
{
    /**
     * @param string $path
     */
    public function syncCommand(string $path)
    {
        $finder = new Finder();
        $finder
            ->name('ext_emconf.php')
            ->followLinks()
            ->depth(0)
            ->ignoreUnreadableDirs()
            ->in($path);

        $extensions = [];
        foreach ($finder as $file) {
            $extensions[] = basename($file->getPath());
        }
        foreach ($finder as $file) {
            $this->outputLine('Found: %s', [$file->getPathname()]);
            $this->syncEmconfWithComposerJson($file, $extensions);
        }
    }

    private function syncEmconfWithComposerJson(SplFileInfo $file, array $extensions)
    {
        $composerJson = $file->getPath() . '/composer.json';
        $_EXTKEY = basename(dirname($composerJson));
        if (file_exists($composerJson)) {
            $jsonManipulator = new JsonManipulator(file_get_contents($composerJson));
        } else {
            $jsonManipulator = new JsonManipulator('');
            $vendor = $this->output->ask('Vendor name: ');
            $jsonManipulator->addProperty('name', $vendor . '/' . str_replace('_', '-', $_EXTKEY));
            $jsonManipulator->addProperty('type', 'typo3-cms-extension');
            $jsonManipulator->addProperty('description', '');
            $jsonManipulator->addProperty('homepage', 'https://typo3.org/extensions/repository/view/' . $_EXTKEY);
            $jsonManipulator->addProperty('license', ['GPL-2.0+']);
            $jsonManipulator->addLink('require', 'typo3/cms-core', '^8.7');
            $jsonManipulator->addSubNode('replace', $_EXTKEY, 'self.version');
            $jsonManipulator->addSubNode('replace', 'typo3-ter/' . str_replace('_', '-', $_EXTKEY), 'self.version');
            $jsonManipulator->addMainKey('autoload', [
                'psr-4' => [
                    ucfirst($vendor) . '\\' . GeneralUtility::underscoredToUpperCamelCase($_EXTKEY) . '\\' => 'Classes'
                ]
            ]);
            $jsonManipulator->addMainKey('autoload-dev', [
                'psr-4' => [
                    ucfirst($vendor) . '\\' . GeneralUtility::underscoredToUpperCamelCase($_EXTKEY) . '\\Tests\\' => 'Tests'
                ]
            ]);
        }

        include (string)$file;
        $emconfData = $EM_CONF[$_EXTKEY];

        $jsonManipulator->addProperty('description', $emconfData['description']);
        $jsonManipulator->addSubNode('extra', 'typo3/cms.extension-key', $_EXTKEY);


        if (isset($emconfData['constraints']['depends']) && is_array($emconfData['constraints']['depends'])) {
            foreach ($emconfData['constraints']['depends'] as  $extKey => $versionRange) {
                $packageName = str_replace('_', '-', $extKey);
                if (in_array($extKey, $extensions, true)) {
                    $jsonManipulator->addLink('require', 'typo3/cms-' . $packageName, $this->convertVersionRangeToPackageVersion($versionRange), true);
                } else {
                    if ($extKey === 'typo3') {
                        $packageName = 'typo3/cms-core';
                    } else {
                        $packageName = 'typo3-ter/' . $packageName;
                    }
                    $jsonManipulator->addLink('require', $packageName, $this->convertVersionRangeToPackageVersion($versionRange), true);
                }
            }
        }

        file_put_contents($composerJson, $jsonManipulator->getContents());
    }

    private function convertVersionRangeToPackageVersion($versionRange)
    {
        list ($lower, $higher) = explode('-', $versionRange);
        $lower = trim($lower) ? '>=' . trim($lower) : '';
        $higher = trim($higher) ? '<=' . trim($higher) : '';
        return trim($lower . ' ' . $higher);
    }
}
