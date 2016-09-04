<?php
foreach (get_included_files() as $includeFile) {
    $includedFilesRaw[$includeFile] = filesize($includeFile);
}

$includedFilesSorted = $includedFilesRaw;
arsort($includedFilesSorted);
$count = count($includedFilesRaw);

?>

<div id="pdw-panel-includedFiles" class="pdw-panel includedFiles">
    <div class="title">
        <h2>Server Info <a class="pdw-panel-close">&times;</a></h2>
    </div>
    <div class="panel-content">
        <h3 class="collapser">included files (size order) [count: <?php echo $count; ?>]</h3>
        <table class="pdw-data-table">
            <thead>
            <tr>
                <th>Size</th>
                <th>File</th> 
            </tr>
            </thead>
            <tbody>
            <?php
            foreach($includedFilesSorted as $filePath => $size):
                echo '<tr><td class="align-right"><pre>[' . str_pad(number_format($size, 0,'', '.'), 7, ' ' , STR_PAD_LEFT ) . ' Bytes]</pre></td><td>' . $filePath . '</td>';
            endforeach;
            ?>
            </tbody>
        </table>
        <h3 class="collapser closed">included files (include order) [count: <?php echo $count; ?>]</h3>
        <table class="pdw-data-table">
            <thead>
            <tr>
                <th>File</th>
                <th>Size</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach($includedFilesRaw as $filePath => $size):
                echo '<tr><td>' . $filePath . '</td><td>' . $debugWidget->getHumanReadableSize($size) . '</td></tr>';
            endforeach;
            ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    $('#pdw-included-files').html(<?php echo (int) $count; ?>);
</script>