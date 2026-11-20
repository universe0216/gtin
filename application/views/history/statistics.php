<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$group = ($group ?? 'location') === 'category' ? 'category' : 'location';
$level = (int) ($level ?? 1);
$date_from = (string) ($date_from ?? '');
$date_to = (string) ($date_to ?? '');
$total = (int) ($total ?? 0);
$groups = is_array($groups ?? NULL) ? $groups : array();

$location_levels = array(
	array('value' => '1', 'label' => app_line('location_province')),
	array('value' => '2', 'label' => app_line('location_county')),
	array('value' => '3', 'label' => app_line('location_city')),
);
$category_levels = array(
	array('value' => '1', 'label' => app_line('category_segment')),
	array('value' => '2', 'label' => app_line('category_family')),
	array('value' => '3', 'label' => app_line('category_class')),
	array('value' => '4', 'label' => app_line('category_brick')),
);
$level_options = $group === 'category' ? $category_levels : $location_levels;

if ($date_from !== '' && $date_to !== '')
{
	$period_label = $date_from.' – '.$date_to;
}
elseif ($date_from !== '')
{
	$period_label = $date_from;
}
elseif ($date_to !== '')
{
	$period_label = $date_to;
}
else
{
	$period_label = app_line('statistics_all_dates');
}
?>
<div class="statistics-page">
	<div class="mb-3">
		<h1 class="h4 mb-1"><?php echo html_escape(app_line('title_statistics')); ?></h1>
		<p class="text-muted small mb-0"><?php echo html_escape(app_line('statistics_note')); ?></p>
	</div>

	<form class="app-panel statistics-filters p-3 mb-3" method="get" action="<?php echo site_url('history/statistics'); ?>">
		<div class="statistics-filter-grid">
			<label class="statistics-filter">
				<span><?php echo html_escape(app_line('statistics_from')); ?></span>
				<input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape($date_from); ?>">
			</label>
			<label class="statistics-filter">
				<span><?php echo html_escape(app_line('statistics_to')); ?></span>
				<input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape($date_to); ?>">
			</label>
			<label class="statistics-filter">
				<span><?php echo html_escape(app_line('statistics_group')); ?></span>
				<select name="group" id="statisticsGroup" class="form-select form-select-sm">
					<option value="location"<?php echo $group === 'location' ? ' selected' : ''; ?>><?php echo html_escape(app_line('statistics_group_location')); ?></option>
					<option value="category"<?php echo $group === 'category' ? ' selected' : ''; ?>><?php echo html_escape(app_line('statistics_group_category')); ?></option>
				</select>
			</label>
			<label class="statistics-filter">
				<span><?php echo html_escape(app_line('statistics_level')); ?></span>
				<select name="level" id="statisticsLevel" class="form-select form-select-sm">
					<?php foreach ($level_options as $option): ?>
						<option value="<?php echo html_escape($option['value']); ?>"<?php echo (string) $level === (string) $option['value'] ? ' selected' : ''; ?>><?php echo html_escape($option['label']); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="statistics-filter statistics-filter-action">
				<button type="submit" class="btn btn-primary btn-sm"><?php echo html_escape(app_line('statistics_apply')); ?></button>
			</div>
		</div>
	</form>

	<div class="statistics-total mb-3">
		<div class="statistics-total-label"><?php echo html_escape(app_line('statistics_products')); ?></div>
		<div class="statistics-total-value"><?php echo number_format($total); ?></div>
		<div class="statistics-total-period"><?php echo html_escape($period_label); ?></div>
	</div>

	<?php if (empty($groups)): ?>
		<div class="app-panel p-4 text-muted"><?php echo html_escape(app_line('statistics_empty')); ?></div>
	<?php else: ?>
		<div class="statistics-groups-meta">
			<?php echo html_escape(str_replace('{count}', number_format(count($groups)), app_line('statistics_groups'))); ?>
		</div>
		<div class="statistics-metrics">
			<?php foreach ($groups as $row): ?>
				<?php
					$count = (int) ($row['total'] ?? 0);
					$percent = $total > 0 ? ($count / $total) * 100 : 0;
					$percent_label = abs($percent - round($percent)) < 0.05
						? (string) (int) round($percent)
						: number_format($percent, 1);
					$share = str_replace('{percent}', $percent_label, app_line('statistics_share'));
					$width = $percent > 0 ? max(2, (int) round($percent)) : 0;
					$name = trim((string) ($row['name'] ?? ''));

					if ($name === '')
					{
						$name = app_line('statistics_unassigned');
					}
				?>
				<article class="statistics-metric">
					<div class="statistics-metric-name"><?php echo html_escape($name); ?></div>
					<div class="statistics-metric-value"><?php echo number_format($count); ?></div>
					<div class="statistics-metric-share"><?php echo html_escape($share); ?></div>
					<div class="statistics-metric-bar" aria-hidden="true"><span style="width: <?php echo $width; ?>%;"></span></div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<script>
(function () {
	var groupSelect = document.getElementById('statisticsGroup');
	var levelSelect = document.getElementById('statisticsLevel');
	var options = <?php echo json_encode(array(
		'location' => $location_levels,
		'category' => $category_levels,
	), JSON_UNESCAPED_UNICODE); ?>;

	if (!groupSelect || !levelSelect) {
		return;
	}

	groupSelect.addEventListener('change', function () {
		var list = options[groupSelect.value] || options.location;
		var current = levelSelect.value;
		levelSelect.innerHTML = '';
		list.forEach(function (item) {
			var option = document.createElement('option');
			option.value = item.value;
			option.textContent = item.label;
			if (String(item.value) === String(current)) {
				option.selected = true;
			}
			levelSelect.appendChild(option);
		});
	});
})();
</script>
