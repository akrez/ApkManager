<?php

ini_set('upload_max_filesize', "1G");
ini_set('post_max_size', "1G");

include './vendor/autoload.php';

function vd(...$ps)
{
    foreach ($ps as $p) {
        var_dump($p);
    }
    die;
}

function filesizeFormatted($path)
{
    $size = filesize($path);
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

class ApkManager
{
    public const PATH_APK = 'apk';
    public const DEFAULT_ICON = 'default.png';

    private static function addresses($type, $packageName = null, $versionCode = null, $size = '')
    {
        switch ($type) {
            case 'importBase':
                $path = [self::PATH_APK, $packageName, $versionCode];
                return implode(DIRECTORY_SEPARATOR, $path);
            case 'import':
                $path = static::addresses('importBase', $packageName, $versionCode);
                file_exists($path) || mkdir($path, 777, true);
                return $path . DIRECTORY_SEPARATOR . 'base' . '.apk';
            case 'importIcons':
                $path = static::addresses('importBase', $packageName, $versionCode);
                file_exists($path) || mkdir($path, 777, true);
                return $path . DIRECTORY_SEPARATOR . $size . '.png';
            case 'importScanFiles':
                return self::PATH_APK . DIRECTORY_SEPARATOR . '*.{apk}';
            default:
                throw new Exception();
        }
    }

    private static function importIcons($apk)
    {
        try {
            foreach ((array) $apk->getResources($apk->getManifest()->getApplication()->getIcon()) as $resource) {
                if ($content = $apk->getStream($resource)) {
                    $content = stream_get_contents($content);
                    $iconSize = getimagesizefromstring($content);
                    if ($iconSize) {
                        $targetPath = self::addresses('importIcons', $apk->getManifest()->getPackageName(), $apk->getManifest()->getVersionCode(), $iconSize[0]);
                        file_put_contents($targetPath, $content);
                    }
                }
                unset($content);
            }
        } catch (Exception $e) {
        } catch (Throwable $t) {
        }
    }

    public static function importScanFiles()
    {
        $targetPath = self::addresses('importScanFiles');
        return glob($targetPath, GLOB_BRACE);
    }


    public static function extractApk($file)
    {
        try {
            $apk = new \ApkParser\Parser($file);
            self::importIcons($apk);
            $targetPath = self::addresses('import', $apk->getManifest()->getPackageName(), $apk->getManifest()->getVersionCode());
            @rename($file, $targetPath);
            @unlink($file);
        } catch (Exception $e) {
        } catch (Throwable $t) {
        }
    }

    public static function importFromBaseDirectory()
    {
        $importScanFiles = self::importScanFiles();
        set_time_limit(count($importScanFiles) * 15 + 15);
        foreach ($importScanFiles as $importScanFile) {
            ApkManager::extractApk($importScanFile);
        }
    }

    public static function search($pattern = '')
    {
        $icons = [];
        $searchedIcons = glob(self::PATH_APK . '/*' . $pattern . '*/*/*.{png}', GLOB_BRACE);
        rsort($searchedIcons, SORT_STRING);
        foreach ($searchedIcons as $searchedIcon) {
            $searchedIconParts = str_ireplace('\\', '/', $searchedIcon);
            $searchedIconParts = explode('/', $searchedIconParts);
            $searchedIconParts = array_reverse($searchedIconParts) + [0 => '', 1 => '', 2 => ''];
            //
            $icons[$searchedIconParts[2]][$searchedIconParts[1]] = $searchedIcon;
        }
        //
        $results = [];
        $searchedApks = glob(self::PATH_APK . '/*' . $pattern . '*/*/{base.apk}', GLOB_BRACE);
        sort($searchedApks, SORT_STRING);
        foreach ($searchedApks as $searchedApk) {
            $searchedApkParts = str_ireplace('\\', '/', $searchedApk);
            $searchedApkParts = explode('/', $searchedApkParts);
            $searchedApkParts = array_reverse($searchedApkParts) + [0 => '', 1 => '', 2 => ''];
            //
            $packageName = $searchedApkParts[2];
            $versionCode = $searchedApkParts[1];
            //
            $results[$packageName][$versionCode] = [
                'url' => $searchedApk,
                'icon' => (isset($icons[$packageName][$versionCode]) ? $icons[$packageName][$versionCode] : self::DEFAULT_ICON),
            ];
        }
        //
        return $results;
    }

    public static function delete($packageName, $versionCode)
    {
        if (preg_match('/^([A-Za-z]{1}[A-Za-z\d_]*\.)+[A-Za-z][A-Za-z\d_]*$/', $packageName) && preg_match('/^[0-9]*$/', $versionCode)) {
            $path = ApkManager::addresses('importBase', $packageName, $versionCode);
            $command = "RMDIR /S /Q " . str_ireplace("/", "\\", __DIR__ . '/' . $path);
            return exec($command);
        }
        return false;
    }
}

$input = $_GET + [
    'action' => '',
    'query' => '',
    'package_name' => '',
    'version_code' => '',
];

if ($input['action']) {
    $redirectTo = './?query=' . $input['query'];
    if ($input['action'] == 'import') {
        ApkManager::importFromBaseDirectory();
    } elseif ($input['action'] == 'delete') {
        ApkManager::delete($input['package_name'], $input['version_code']);
    } elseif ($input['action'] == 'upload') {
        if ($_FILES['apks']) {
            foreach ($_FILES['apks']['tmp_name'] as $file) {
                ApkManager::extractApk($file);
            }
        }
    }
    header('Location: ' . $redirectTo);
    die;
}
?>
<!DOCTYPE html>
<html lang="fa-IR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>اندرویدی‌ها</title>
    <link rel="stylesheet" href="web/css/bootstrap.min.css">
    <link rel="stylesheet" href="web/css/bootstrap-rtl.min.css">
    <link rel="stylesheet" href="web/css/font-sahel.css">
    <style>
        html,
        body {
            font-family: 'Sahel' !important;
            -moz-osx-font-smoothing: grayscale;
            -webkit-font-smoothing: antialiased;
            height: 100%;
            direction: rtl;
        }

        .akrez-card-text {
            text-decoration: none;
            text-align: left;
            direction: ltr;
            position: absolute;
            margin-right: 15px;
            margin-left: 15px;
            left: 0;
            background: rgba(0, 0, 0, 0.65);
            color: white;
            padding: 0.5em;
            line-height: 1.25em;
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark static-top bg-primary">
        <div class="container-fluid">
            <a href="./" class="navbar-brand">اندرویدی‌ها</a>
            <button type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler collapsed">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <form class="form-inline" action="" method="get">
                            <div class="input-group">
                                <input name="query" type="text" class="form-control" value="<?= $input['query'] ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-success">جستجو</button>
                                </div>
                            </div>
                        </form>
                    </li>
                </ul>
                <ul class="navbar-nav mr-auto">
                    <?php if (ApkManager::importScanFiles()) : ?>
                        <li class="nav-item">
                            <a class="btn btn-primary nav-link" href="./?action=import">بروزرسانی</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="btn btn-primary nav-link" onclick="$('#upload-files-input').click();">آپلود</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="container mt-2">
        <div class="row">
            <table class="table table-bordered table-sm">
                <tr>
                    <th>Package Name</th>
                    <th>Icon</th>
                    <th>Size</th>
                    <th>Version Code</th>
                    <th></th>
                    <th></th>
                </tr>
                <?php
                foreach (ApkManager::search(strtolower(trim($input['query']))) as $packageName => $versionCodes) :
                    $isFirstItem = true;
                    foreach ($versionCodes as $versionCode => $file) :
                ?>
                        <tr class="<?= count($versionCodes) > 1 ? 'table-danger' : '' ?>">
                            <?php if ($isFirstItem) : ?>
                                <td rowspan="<?= count($versionCodes) ?>">
                                    <?= $packageName ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <img style="max-width: 28px;" class="card-img-top img-fluid" src="<?= $file['icon'] ?>" />
                            </td>
                            <td>
                                <?= filesizeFormatted($file['url']) ?>
                            </td>
                            <td>
                                <?= $versionCode ?>
                            </td>
                            <td>
                                <a href="./?action=delete&package_name=<?= $packageName ?>&version_code=<?= $versionCode ?>">
                                    حذف
                                </a>
                            </td>
                            <td>
                                <a href="<?= ($file['url']) ?>">
                                    دانلود
                                </a>
                            </td>
                        </tr>
                <?php
                        $isFirstItem = false;
                    endforeach;
                endforeach;
                ?>
            </table>
        </div>
    </div>

    <form method="post" action="./?action=upload" enctype="multipart/form-data" onchange="this.submit()" class="d-none">
        <input name="apks[]" type="file" multiple="multiple" id="upload-files-input" />
    </form>

    <!-- Bootstrap core JavaScript -->
    <script src="web/js/jquery.min.js"></script>
    <script src="web/js/bootstrap.bundle.min.js"></script>

</body>

</html>