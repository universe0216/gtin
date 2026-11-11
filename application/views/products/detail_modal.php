<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$product_detail = $product_detail ?? ($detail_config ?? array());
?>
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="productDetailModalLabel"><?php echo html_escape(app_line('title_products')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form id="productDetailForm">
				<div class="modal-body">
					<div id="productDetailLoading" class="text-center text-muted py-5 d-none">
						<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
						<?php echo html_escape(app_line('jqx_load')); ?>
					</div>
					<div id="productDetailAlert" class="alert d-none" role="alert"></div>
					<div id="productDetailFormWrap" class="entity-detail-info-form"></div>
				</div>
				<div class="modal-footer">
					<?php if ( ! empty($product_detail['canDelete'])): ?>
						<button type="button" class="btn btn-outline-danger me-auto" id="productDetailDeleteBtn" data-mdb-ripple-init disabled>
							<i class="fas fa-trash me-1"></i> <?php echo html_escape(app_line('common_delete')); ?>
						</button>
					<?php endif; ?>
					<?php if ( ! empty($product_detail['canRestore'])): ?>
						<button type="button" class="btn btn-success me-auto" id="productDetailRestoreBtn" data-mdb-ripple-init disabled>
							<i class="fas fa-rotate-left me-1"></i> <?php echo html_escape(app_line('common_restore')); ?>
						</button>
					<?php endif; ?>
					<?php if ( ! empty($product_detail['downloadUrl'])): ?>
						<button type="button" class="btn btn-primary" id="productDetailDownloadBtn" data-mdb-ripple-init disabled>
							<i class="fas fa-download me-1"></i> <?php echo html_escape(app_line('common_download')); ?>
						</button>
					<?php endif; ?>
					<?php if ( ! empty($product_detail['canEdit'])): ?>
						<button type="submit" class="btn btn-primary" id="productDetailSaveBtn" data-mdb-ripple-init disabled>
							<span class="product-detail-save-label"><?php echo html_escape(app_line('common_save')); ?></span>
							<span class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>
</div>
