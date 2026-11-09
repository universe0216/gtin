<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="tools-checksum-page">
	<h1 class="h4 mb-3"><?php echo html_escape(app_line('title_verify_checksum')); ?></h1>
	<div class="app-panel p-4 tools-checksum-panel">
		<div class="mb-3">
			<label class="form-label" for="checksumType"><?php echo html_escape(app_line('checksum_type')); ?></label>
			<select class="form-select" id="checksumType">
				<?php foreach ($barcode_types as $type): ?>
					<option value="<?php echo html_escape($type['key']); ?>" data-length="<?php echo (int) $type['length']; ?>">
						<?php echo html_escape($type['label']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="mb-3">
			<label class="form-label" for="checksumBody"><?php echo html_escape(app_line('checksum_body')); ?></label>
			<input
				type="text"
				class="form-control"
				id="checksumBody"
				inputmode="numeric"
				autocomplete="off"
				spellcheck="false"
			>
			<p class="text-muted small mt-2 mb-0" id="checksumHint"></p>
		</div>
		<div class="mb-0">
			<label class="form-label" for="checksumDigit"><?php echo html_escape(app_line('checksum_digit')); ?></label>
			<input
				type="text"
				class="form-control"
				id="checksumDigit"
				readonly
				tabindex="-1"
			>
		</div>
	</div>
</div>
