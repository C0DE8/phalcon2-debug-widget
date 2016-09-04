<?php
	$profiles = $debugWidget->getProfiler()->getProfiles();
	$profiles = !$profiles ? array() : $profiles;
?>
<div id="pdw-panel-db" class="pdw-panel db">
	<div class="title">
		<h2>Database Info <a class="pdw-panel-close">&times;</a></h2>
	</div>
	<div class="panel-content">
		<h3 class="collapser">SQL Queries</h3>
		<table class='pdw-data-table'>
			<thead>
				<tr>
					<th>Query</th>
					<th style="width: 100px">Time (s)</th>
				</tr>
			</thead>
			<tbody>
			<?php
				$total = 0;
				foreach ($profiles as $profile):
					$time = $profile->getTotalElapsedSeconds();
					$total += $time;
					echo "<tr>";
						echo "<td><pre><code class='language-sql'>" . $profile->getSQLStatement() . "</code></pre></td>";
						echo "<td><pre>" . print_r($profile->getSQLVariables(), true) . "</pre></td>";
						echo "<td>" . round($time, 6) . "</td>";
					echo "</tr>";
				endforeach;
			?>
			<tr>
				<td colspan="2"><strong>Total:</strong></td>
				<td><strong><?php echo round($total, 6); ?></strong></td>
			</tr>
			</tbody>
		</table>
<?php
        $dbs = $debugWidget->getServices('db');
        foreach ($dbs as $dbName) {
                $db = $debugWidget->getDI()->get($dbName);
                $descriptors = $db->getDescriptor();
?>
		<h3 class="collapser">DB Server</h3>
		<table class='pdw-data-table'>
			<tbody>
			<tr>
				<td>Type</td>
				<td><?php echo $db->getType(); ?></td>
			</tr>
			<?php
				foreach($descriptors as $k=>$v):
					echo "<tr>";
					echo "<td>{$k}</td>";
					if (is_array($v)) {
						echo "<td><pre>" . print_r($v, true) . "</pre></td>";
					} else {
						echo "<td>" . (($k != 'password') ? $v : '********') . "</td>";
					}
					echo "</tr>";
				endforeach;
			?>
			</tbody>
		</table>
<?php
}
?>
	</div>
</div>
