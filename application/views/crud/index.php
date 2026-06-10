<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$list_fields = array_filter($fields, function ($field) {
	return ($field['type'] ?? 'text') !== 'hidden';
});
?>
<div class="d-flex justify-content-between align-items-center mb-4">
	<h1 class="h3 mb-0"><?php echo html_escape($title); ?></h1>
	<button type="button" class="btn btn-primary" id="btnAddRecord" data-mdb-ripple-init>
		<i class="fas fa-plus me-1"></i> Add New
	</button>
</div>

<div class="card shadow-sm">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-hover mb-0" id="crudTable">
				<thead class="table-light">
					<tr>
						<th scope="col">#</th>
						<?php foreach ($list_fields as $field): ?>
							<th scope="col"><?php echo html_escape($field['label']); ?></th>
						<?php endforeach; ?>
						<th scope="col" class="text-end" style="width: 140px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($records)): ?>
						<tr id="emptyRow">
							<td colspan="<?php echo count($list_fields) + 2; ?>" class="text-center text-muted py-4">
								No records found. Click "Add New" to create one.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($records as $record): ?>
							<tr data-id="<?php echo (int) $record['id']; ?>">
								<td><?php echo (int) $record['id']; ?></td>
								<?php foreach ($list_fields as $field): ?>
									<td><?php echo html_escape($record[$field['name']] ?? ''); ?></td>
								<?php endforeach; ?>
								<td class="text-end">
									<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-mdb-ripple-init>
										<i class="fas fa-pen"></i>
									</button>
									<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-mdb-ripple-init>
										<i class="fas fa-trash"></i>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
$this->load->view('partials/basic_modal', array(
	'title'  => $title,
	'entity' => $entity,
	'fields' => $fields,
));
?>

<script>
	window.CRUD_CONFIG = <?php echo json_encode(array(
		'entity'      => $entity,
		'entityLabel' => $title,
		'fields'      => $list_fields,
		'baseUrl'     => site_url($entity),
	)); ?>;
</script>
