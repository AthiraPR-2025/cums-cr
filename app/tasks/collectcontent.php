<?php
namespace Fuel\Tasks;

use Config;
use Cums\GitHelper;
use Cums\DB\Content;

class CollectContent
{
    public function run()
    {
        try {
            Config::load('cums', true);

            $repos = Config::get('cums.local_repos');
            $git   = Config::get('cums.git');

            // Pull latest from GitHub
            foreach (['ot', 'og'] as $key) {
                GitHelper::cloneRepo($git[$key . '_repo_url'], $repos[$key], $git['pat']);
                GitHelper::pullRepo($repos[$key]);
            }

            $multi_parameters = [];
            $maxDepth = 3;

            foreach ($repos as $domainKey => $rootPath) {
                // Prepare filtered iterator with max depth
                $directoryIterator = new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS);

                $filteredIterator = new \RecursiveCallbackFilterIterator(
                    $directoryIterator,
                    function ($file, $key, $iterator) use ($rootPath, $maxDepth) {
                        $relativePath = str_replace($rootPath, '', $file->getPathname());
                        $depth = substr_count($relativePath, DIRECTORY_SEPARATOR);
                        return $depth <= $maxDepth;
                    }
                );

                $iterator = new \RecursiveIteratorIterator(
                    $filteredIterator,
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->getFilename() === 'index.html') {
                        $path = $file->getRealPath();

                        // Shell-level check using `find` to ensure it's a regular file
                        $escapedPath = escapeshellarg($path);
                        $command = "find {$escapedPath} -maxdepth 0 -xtype f";
                        exec($command, $output, $status);

                        if ($status !== 0 || empty($output)) {
                            // Skip invalid or non-regular files
                            continue;
                        }

                        // Read and process the HTML file
                        $html = file_get_contents($path);
                        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'auto');

                        $dom = new \DOMDocument;
                        @$dom->loadHTML($html);
                        $titleNode = $dom->getElementsByTagName('title')->item(0);

                        $multi_parameters[] = [
                            'domainid'          => $domainKey,
                            'filename'          => basename($path),
                            'path'              => $path,
                            'apemp'             => 'system',
                            'title'             => $titleNode ? $titleNode->textContent : 'title無し',
                            'contentupdatedate' => date('Y-m-d H:i:s', filemtime($path)),
                        ];
                    }
                }
            }

            Content::refresh($multi_parameters);

            echo "Content collected and stored.\n";
        } catch (\Exception $e) {
            \Log::error(__FILE__ . ':' . __LINE__);
            \Log::error('$e->getCode():' . var_export($e->getCode(), true));
            \Log::error('$e->getMessage():' . var_export($e->getMessage(), true));
            exit("Error: " . $e->getMessage() . "\n");
        }
    }
}
