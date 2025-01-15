<?php
// GET параметр, который необходимо передать в скрипт, для его запуска
define('STARTER', 'run');

// Самоудаление скрипта, если он добавлен более суток назад
if (time() > (filectime(__FILE__) + 86400)) {
    @unlink(__FILE__);
    exit('file timeout');
}
// Самоудаление скрипта по get запросу
if (isset($_GET['delete'])) {
    if (is_writable(__FILE__)) {
        unlink(__FILE__);
        exit('deleted');
    } else {
        exit('Error! no permission to delete');
    }
}

// Для запуска, не забудь добавить GET параметр в адресную строку
if (!isset($_GET[STARTER])) {
    die();
}

/*
    Форматирование размера файла
*/
function format_size($size)
{
    $metrics = array('z','y','x','w','t');// B KB MB GB TB
    $metric = 0;
    while (floor($size / 1024) > 0) {
        ++$metric;
        $size /= 1024;
    }
    $exp = isset($metrics[$metric]) ? $metrics[$metric] : '??';
    $size = round($size, 1);
    $ret =  "<$exp>$size</$exp>";
    return $ret;
}

/*
    Экранирование названия файла
*/
function escape_name($name)
{
    return htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE);
}

/*
    Проверка на подходящий для сканирования файл
*/
function get_file_size($file)
{
    if (is_readable($file)) {
        $filesize = filesize($file);
        if ($filesize) {
            return $filesize;
        }
    }

    return 0;
}

/*
    Рекурсивное сканирование файлов
*/
function scan_recursive($directory)
{
    global $start_time, $interrupted;

    $dirs = array();
    $files = array();
    $size = 0;

    if (is_readable($directory)) {
        $dir = array_diff(scandir($directory), array('.', '..'));
        foreach ($dir as $fname) {
            $filename = $directory.DIRECTORY_SEPARATOR.$fname;

            if (is_link($filename)) {
                continue;
            } elseif (is_dir($filename)) {
                $subfolder = scan_recursive($filename);
                if ($subfolder['size']) {
                    $dirs[$subfolder['size']][] = $subfolder;
                    $size += $subfolder['size'];
                }
            } else {
                $filesize = get_file_size($filename);
                if ($filesize) {
                    $files[$filesize][] = $fname;
                    $size += $filesize;
                }
            }

            if ((time() - $start_time) > 55) {
                $interrupted = true;
                return;
            }
        }
    }

    // Сортировка
    krsort($dirs);
    krsort($files);

    return array('name' => basename($directory), 'size' => $size, 'dirs' => $dirs, 'files' => $files);
}

/*
    Рекурсивное отображение списка файлов
*/
function show_recursive($dir)
{
    $is_root = $dir['name'] == '.';
    if ($is_root) {
        $dir['name'] = 'Current directory';
    }

    echo '<m', $is_root ? ' open' : '', '>', format_size($dir['size']), '<n>', $dir['name'], '</n></m>';
    echo '<d>';

    foreach ($dir['dirs'] as $subdirnames) {
        foreach ($subdirnames as $subdirname) {
            show_recursive($subdirname);
        }
    }
    foreach ($dir['files'] as $filesize => $filenames) {
        foreach ($filenames as $filename) {
            echo '<r>', format_size($filesize), '<n>', $filename, '</n></r>';
        }
    }

    echo '</d>';
}

// запуск таймера, чтобы скрипт не сканировал больше минуты
$start_time = time();

// Выбор режима сканирования
$started = false;
if (isset($_POST['submit'])) {
    $started = true;
}

// Флаг, прервано ли сканирование из-за таймаута
$interrupted = false;

// Защита от межсайтового скриптинга
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Установка рекомендуемого лимита оперативной памяти и времени выполнения
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '60');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>weigher.php</title>
<style>*,*:before,*:after{box-sizing: inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:800px;margin:10px auto 60px}form,p,h3,output{text-align:center;font-size:small;user-select:none}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px}output,r,m{display:block}d{display:none;border-left:15px solid #d4d9dd;border-bottom:6px solid #d4d9dd;padding-left:3px;border-radius:3px;margin:5px 0}r,m{margin:5px 0;font-size:small;background:#f1f1f1;border-radius:3px}m{font-weight: bold;cursor:pointer;text-decoration: underline;position:relative}z,y,x,w,t{background:#d4d9dd;padding:5px 6px;border-radius:3px;font-size:medium;margin:0;min-width:80px;display:inline-block}n{overflow-wrap:anywhere;padding: 5px}m[open] + d{display: block}m[open]::before{content: "";background:#d4d9dd;display:block;width:15px;height:8px;position:absolute;left:0;bottom:-7px}z::after{content:" B"}y::after{content:" KB"}x::after{content:" MB"}w::after{content:" GB"}t::after{content:" TB"}r z,r y{background:#e5e5e5}r x{background:#f1e9ba}r w,r t{background:#ff8b8b}</style>
</head>
<body>
<form method="POST">
<p>It's time to <button type="submit" name="submit">start</button> weighing in!</p>
<p>Don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
</form>
<main>
<?php
if ($started) {
    // Запуск
    $files = scan_recursive('.');
    show_recursive($files);

    if ($interrupted) {
        echo '<output>Weighing time has expired!</output>';
    } else {
        echo '<h3>Weighing completed</h3>';
    }
}
?>
</main>
<script>document.addEventListener("DOMContentLoaded",(e)=>{document.querySelectorAll('m').forEach(m=>{m.addEventListener('click',function(){if(this.hasAttribute('open'))this.removeAttribute('open');else this.setAttribute('open', '');});});});</script>
</body>
</html>
