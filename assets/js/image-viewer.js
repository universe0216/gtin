(function (global) {
	'use strict';

	function ImageViewer(viewportEl, options) {
		if (!viewportEl) {
			return;
		}

		this.viewport = viewportEl;
		this.stage = viewportEl.querySelector('.image-viewer-stage');
		this.image = viewportEl.querySelector('.image-viewer-target');
		this.options = Object.assign({
			minScale: 0.5,
			maxScale: 6,
			zoomStep: 0.12,
		}, options || {});

		this.scale = 1;
		this.translateX = 0;
		this.translateY = 0;
		this.isDragging = false;
		this.activePointerId = null;
		this.dragStartX = 0;
		this.dragStartY = 0;
		this.originTranslateX = 0;
		this.originTranslateY = 0;

		this.onWheel = this.onWheel.bind(this);
		this.onPointerDown = this.onPointerDown.bind(this);
		this.onDocumentPointerMove = this.onDocumentPointerMove.bind(this);
		this.onDocumentPointerUp = this.onDocumentPointerUp.bind(this);
		this.onDragStart = this.onDragStart.bind(this);

		this.bindEvents();
		this.reset();
	}

	ImageViewer.prototype.bindEvents = function () {
		this.viewport.addEventListener('wheel', this.onWheel, { passive: false });
		this.viewport.addEventListener('pointerdown', this.onPointerDown);

		if (this.image) {
			this.image.addEventListener('dragstart', this.onDragStart);
		}
	};

	ImageViewer.prototype.unbindEvents = function () {
		this.stopDragging();

		this.viewport.removeEventListener('wheel', this.onWheel);
		this.viewport.removeEventListener('pointerdown', this.onPointerDown);

		if (this.image) {
			this.image.removeEventListener('dragstart', this.onDragStart);
		}
	};

	ImageViewer.prototype.onDragStart = function (event) {
		event.preventDefault();
	};

	ImageViewer.prototype.applyTransform = function () {
		if (!this.stage) {
			return;
		}

		this.stage.style.transform =
			'translate3d(' + this.translateX + 'px, ' + this.translateY + 'px, 0) scale(' + this.scale + ')';
	};

	ImageViewer.prototype.reset = function () {
		this.stopDragging();
		this.scale = 1;
		this.translateX = 0;
		this.translateY = 0;
		this.viewport.classList.remove('is-dragging');

		if (this.stage) {
			this.stage.classList.remove('is-moving');
		}

		this.applyTransform();
	};

	ImageViewer.prototype.setImage = function (url) {
		if (!this.image) {
			return;
		}

		this.image.src = url;
		this.reset();
	};

	ImageViewer.prototype.startDragging = function (event) {
		this.isDragging = true;
		this.activePointerId = event.pointerId;
		this.dragStartX = event.clientX;
		this.dragStartY = event.clientY;
		this.originTranslateX = this.translateX;
		this.originTranslateY = this.translateY;
		this.viewport.classList.add('is-dragging');

		if (this.stage) {
			this.stage.classList.add('is-moving');
		}

		document.addEventListener('pointermove', this.onDocumentPointerMove);
		document.addEventListener('pointerup', this.onDocumentPointerUp);
		document.addEventListener('pointercancel', this.onDocumentPointerUp);
	};

	ImageViewer.prototype.stopDragging = function () {
		if (!this.isDragging) {
			return;
		}

		this.isDragging = false;
		this.activePointerId = null;
		this.viewport.classList.remove('is-dragging');

		if (this.stage) {
			this.stage.classList.remove('is-moving');
		}

		document.removeEventListener('pointermove', this.onDocumentPointerMove);
		document.removeEventListener('pointerup', this.onDocumentPointerUp);
		document.removeEventListener('pointercancel', this.onDocumentPointerUp);
	};

	ImageViewer.prototype.onWheel = function (event) {
		event.preventDefault();

		const rect = this.viewport.getBoundingClientRect();
		const centerX = rect.width / 2;
		const centerY = rect.height / 2;
		const pointX = event.clientX - rect.left - centerX;
		const pointY = event.clientY - rect.top - centerY;
		const direction = event.deltaY < 0 ? 1 : -1;
		const nextScale = Math.min(
			this.options.maxScale,
			Math.max(this.options.minScale, this.scale + direction * this.options.zoomStep)
		);
		const ratio = nextScale / this.scale;

		this.translateX = pointX - (pointX - this.translateX) * ratio;
		this.translateY = pointY - (pointY - this.translateY) * ratio;
		this.scale = nextScale;
		this.applyTransform();
	};

	ImageViewer.prototype.onPointerDown = function (event) {
		if (!this.image || event.button !== 0) {
			return;
		}

		event.preventDefault();
		this.startDragging(event);
	};

	ImageViewer.prototype.onDocumentPointerMove = function (event) {
		if (!this.isDragging || event.pointerId !== this.activePointerId) {
			return;
		}

		event.preventDefault();
		this.translateX = this.originTranslateX + (event.clientX - this.dragStartX);
		this.translateY = this.originTranslateY + (event.clientY - this.dragStartY);
		this.applyTransform();
	};

	ImageViewer.prototype.onDocumentPointerUp = function (event) {
		if (!this.isDragging || event.pointerId !== this.activePointerId) {
			return;
		}

		this.stopDragging();
	};

	ImageViewer.prototype.destroy = function () {
		this.unbindEvents();
		this.viewport = null;
		this.stage = null;
		this.image = null;
	};

	global.ImageViewer = ImageViewer;
})(window);
