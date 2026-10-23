<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="entity-list-page" id="entityListPage">
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

	<div class="app-panel p-2 entity-list-panel">
		<div class="entity-list-toolbar d-flex flex-wrap align-items-center gap-2 px-2 py-2 mb-2">
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
						aria-label="<?php echo html_escape(app_line('search_aria', $title)); ?>"
					>
					<button type="button" class="btn btn-outline-secondary d-none" id="entityListClearBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_clear')); ?></button>
				</div>
			</form>
			<p class="text-muted small mb-0 entity-list-toolbar-meta d-none" id="entityListToolbarMeta"></p>
		</div>

		<div id="entityListGrid"></div>
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
