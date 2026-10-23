<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$list_fields = array_filter($fields, function ($field) {
	return ($field['type'] ?? 'text') !== 'hidden';
});
$can_edit = $can_edit ?? TRUE;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
	<h1 class="h3 mb-0"><?php echo html_escape($title); ?></h1>
	<?php if ($can_edit): ?>
		<?php if ( ! empty($add_url)): ?>
			<a href="<?php echo html_escape($add_url); ?>" class="btn btn-primary" data-mdb-ripple-init>
				<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('common_add_new')); ?>
			</a>
		<?php else: ?>
			<button type="button" class="btn btn-primary" id="btnAddRecord" data-mdb-ripple-init>
				<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('common_add_new')); ?>
			</button>
		<?php endif; ?>
	<?php endif; ?>
</div>

<div class="card shadow-sm app-panel">
	<div class="card-body p-0">
		<div id="crudGrid"></div>
	</div>
</div>

<?php if ($can_edit): ?>
<?php
$this->load->view('partials/basic_modal', array(
	'title'  => $title,
	'entity' => $entity,
	'fields' => $fields,
));
?>
<?php endif; ?>

<script>
	window.CRUD_CONFIG = <?php echo json_encode(array(
		'entity'      => $entity,
		'entityLabel' => $title,
		'fields'      => array_values($list_fields),
		'baseUrl'     => site_url($entity),
		'canEdit'     => $can_edit,
		'records'     => array_map(function ($record) use ($list_fields) {
			$row = array('id' => isset($record['id']) ? (int) $record['id'] : null);

			foreach ($list_fields as $field) {
				$row[$field['name']] = $record[$field['name']] ?? '';
			}

			return $row;
		}, $records),
	)); ?>;
</script>
