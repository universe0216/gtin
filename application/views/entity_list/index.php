<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$column_count = count($list_columns) + 1;
?>
<div
	class="entity-list-page"
	id="entityListPage"
	style="--entity-list-page-rows: <?php echo (int) $list_per_page; ?>;"
>
	<div class="entity-list-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		</div>
		<?php if ( ! empty($can_edit) && ! empty($add_url)): ?>
			<a href="<?php echo html_escape($add_url); ?>" class="btn btn-sm btn-primary" data-mdb-ripple-init>
				<i class="fas fa-plus me-1"></i> <?php echo html_escape($add_label ?? 'Add New'); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="app-panel p-0 entity-list-panel">
		<div class="entity-list-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 border-bottom border-secondary border-opacity-25">
			<form class="entity-list-search-form d-flex flex-wrap align-items-center gap-2 flex-grow-1" role="search" id="entityListSearchForm">
				<div class="input-group input-group-sm entity-list-search-input-group">
					<span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
					<input
						type="search"
						name="q"
						class="form-control"
						id="entityListSearchInput"
						placeholder="<?php echo html_escape($list_search_placeholder); ?>"
						autocomplete="off"
						aria-label="Search <?php echo html_escape($title); ?>"
					>
					<button type="button" class="btn btn-outline-secondary d-none" id="entityListClearBtn" data-mdb-ripple-init>Clear</button>
				</div>
			</form>
			<p class="text-muted small mb-0 entity-list-toolbar-meta d-none" id="entityListToolbarMeta"></p>
		</div>

		<div class="entity-list-table-scroll procedure-table-scroll position-relative">
			<div id="entityListLoading" class="entity-list-loading d-none">
				<span>
					<div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>
					Loading...
				</span>
			</div>
			<table class="table table-hover align-middle mb-0 procedure-data-table">
				<thead>
					<tr id="entityListTableHeadRow"></tr>
				</thead>
				<tbody id="entityListTableBody">
					<tr>
						<td colspan="<?php echo (int) $column_count; ?>" class="text-center text-muted py-4">Loading...</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div id="entityListPagination" class="entity-list-pagination app-pagination d-flex flex-wrap justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
			<div class="d-flex flex-wrap align-items-center gap-3">
				<p class="text-muted small mb-0" id="entityListPageMeta"></p>
				<div class="entity-list-page-size d-flex align-items-center gap-2">
					<label class="text-muted small mb-0" for="entityListPerPageSelect">Rows per page</label>
					<select class="form-select form-select-sm" id="entityListPerPageSelect" aria-label="Rows per page">
						<?php foreach ($list_per_page_options as $option): ?>
							<option value="<?php echo (int) $option; ?>"<?php echo (int) $option === (int) $list_per_page ? ' selected' : ''; ?>>
								<?php echo (int) $option; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<nav class="app-pagination-nav d-none" id="entityListPaginationNav" aria-label="Entity list pages">
				<ul class="pagination pagination-sm pagination-circle mb-0" id="entityListPaginationList"></ul>
			</nav>
		</div>
	</div>
</div>

<?php if ( ! empty($detail_modal_partial)): ?>
	<?php $this->load->view($detail_modal_partial, array('detail_config' => $detail_config ?? array())); ?>
<?php endif; ?>

<script>
	window.ENTITY_LIST_CONFIG = <?php echo json_encode(array(
		'apiUrl'            => $list_api_url,
		'columns'           => $list_columns,
		'perPage'           => (int) $list_per_page,
		'perPageOptions'    => array_values($list_per_page_options),
		'emptyMessage'      => $list_empty_message,
		'emptySearchMessage'=> $list_empty_search_message,
		'detail'            => $detail_config ?? NULL,
	)); ?>;
</script>
