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
		<button type="button" class="btn btn-primary" id="btnAddRecord" data-mdb-ripple-init>
			<i class="fas fa-plus me-1"></i> Add New
		</button>
	<?php endif; ?>
</div>

<div class="card shadow-sm app-panel">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-hover table-dark mb-0" id="crudTable">
				<thead>
					<tr>
						<th scope="col">#</th>
						<?php foreach ($list_fields as $field): ?>
							<th scope="col"><?php echo html_escape($field['label']); ?></th>
						<?php endforeach; ?>
						<?php if ($can_edit): ?>
							<th scope="col" class="text-end" style="width: 140px;">Actions</th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($records)): ?>
						<tr id="emptyRow">
							<td colspan="<?php echo count($list_fields) + 1 + ($can_edit ? 1 : 0); ?>" class="text-center text-muted py-4">
								No records found.<?php echo $can_edit ? ' Click "Add New" to create one.' : ''; ?>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($records as $record): ?>
							<tr data-id="<?php echo (int) $record['id']; ?>">
								<td><?php echo (int) $record['id']; ?></td>
								<?php foreach ($list_fields as $field): ?>
									<td><?php echo html_escape($record[$field['name']] ?? ''); ?></td>
								<?php endforeach; ?>
								<?php if ($can_edit): ?>
									<td class="text-end">
										<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-mdb-ripple-init>
											<i class="fas fa-pen"></i>
										</button>
										<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-mdb-ripple-init>
											<i class="fas fa-trash"></i>
										</button>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
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
		'fields'      => $list_fields,
		'baseUrl'     => site_url($entity),
		'canEdit'     => $can_edit,
	)); ?>;
</script>
