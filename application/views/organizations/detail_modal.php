<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="modal fade entity-detail-modal history-procedure-modal" id="organizationDetailModal" tabindex="-1" aria-labelledby="organizationDetailModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header history-procedure-modal-header">
				<h6 class="modal-title mb-0" id="organizationDetailModalLabel">
					<i class="fas fa-building me-1 text-primary"></i>
					<span id="organizationDetailModalTitle">—</span>
				</h6>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0 history-procedure-modal-body entity-detail-modal-body">
				<ul class="nav nav-tabs procedure-tabs px-3 pt-3" id="organizationDetailTabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button
							class="nav-link active"
							id="organizationInfoTabBtn"
							data-mdb-tab-init
							data-mdb-target="#organizationInfoTab"
							type="button"
							role="tab"
							aria-controls="organizationInfoTab"
							aria-selected="true"
						>Organization Info</button>
					</li>
					<li class="nav-item" role="presentation">
						<button
							class="nav-link"
							id="organizationProductsTabBtn"
							data-mdb-tab-init
							data-mdb-target="#organizationProductsTab"
							type="button"
							role="tab"
							aria-controls="organizationProductsTab"
							aria-selected="false"
						>Products</button>
					</li>
				</ul>

				<div class="tab-content entity-detail-tab-content" id="organizationDetailTabContent">
					<div class="tab-pane fade show active" id="organizationInfoTab" role="tabpanel" aria-labelledby="organizationInfoTabBtn">
						<div id="organizationDetailInfoLoading" class="text-center text-muted py-5 d-none">
							<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
							Loading organization info...
						</div>
						<div id="organizationDetailInfoWrap" class="entity-detail-info-wrap px-4 py-4"></div>
					</div>

					<div class="tab-pane fade" id="organizationProductsTab" role="tabpanel" aria-labelledby="organizationProductsTabBtn">
						<div class="entity-detail-products-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 border-bottom border-secondary border-opacity-25">
							<div class="input-group input-group-sm entity-list-search-input-group">
								<span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
								<input
									type="search"
									class="form-control"
									id="organizationProductsSearchInput"
									placeholder="Search products..."
									autocomplete="off"
									aria-label="Search organization products"
								>
								<button type="button" class="btn btn-outline-secondary d-none" id="organizationProductsClearBtn" data-mdb-ripple-init>Clear</button>
							</div>
							<p class="text-muted small mb-0 d-none" id="organizationProductsMeta"></p>
						</div>

						<div class="entity-detail-products-table procedure-table-scroll position-relative">
							<div id="organizationProductsLoading" class="entity-list-loading d-none">
								<span>
									<div class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></div>
									Loading products...
								</span>
							</div>
							<table class="table table-hover align-middle mb-0 procedure-data-table">
								<thead>
									<tr id="organizationProductsTableHead"></tr>
								</thead>
								<tbody id="organizationProductsTableBody">
									<tr>
										<td class="text-center text-muted py-4">Select an organization to view products.</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div id="organizationProductsPagination" class="entity-list-pagination app-pagination d-flex flex-wrap justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
							<p class="text-muted small mb-0" id="organizationProductsPageMeta"></p>
							<nav class="app-pagination-nav d-none" id="organizationProductsPaginationNav" aria-label="Organization product pages">
								<ul class="pagination pagination-sm pagination-circle mb-0" id="organizationProductsPaginationList"></ul>
							</nav>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer history-procedure-modal-footer">
				<span class="text-muted small me-auto" id="organizationDetailFooterMeta"></span>
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Close</button>
			</div>
		</div>
	</div>
</div>
