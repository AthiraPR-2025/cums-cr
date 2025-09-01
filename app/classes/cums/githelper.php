<?php
namespace Cums;

class GitHelper
{
    public static function cloneRepo($repoUrl, $localPath, $pat)
    {
        if (!is_dir($localPath . '/.git')) {
            $authUrl = str_replace('https://', "https://{$pat}@", $repoUrl);
            $cmd = "git clone {$authUrl} {$localPath} 2>&1";
            exec($cmd, $output, $status);

            if ($status !== 0) {
                echo "Clone failed:\n" . implode("\n", $output) . "\n";
                return false;
            }

            echo "Cloned repo into: {$localPath}\n";
        } else {
            echo "Repo already exists: {$localPath}\n";
        }

        return true;
    }

    public static function pullRepo($localPath)
    {
        if (!is_dir($localPath . '/.git')) {
            echo "Git repo not found at: {$localPath}\n";
            return false;
        }

        $cmd = "cd {$localPath} && git pull 2>&1";
        exec($cmd, $output, $status);

        if ($status !== 0) {
            echo "Pull failed:\n" . implode("\n", $output) . "\n";
            return false;
        }

        echo "Pulled latest changes in: {$localPath}\n";
        return true;
    }
}
