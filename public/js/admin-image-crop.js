/**
 * Admin Image Crop — Cropper.js integration for EasyAdmin VichUploader fields.
 *
 * Targets fields with CSS class `.field-image-crop` (set via row_attr).
 * Provides drag & drop, preview, and a crop modal with WebP conversion.
 *
 * Supports per-field configuration via data attributes on `.field-image-crop`:
 *   data-crop-ratio      — aspect ratio as "w/h" (default "16/9")
 *   data-crop-max-width  — max output width in px (default 1200)
 *   data-crop-max-height — max output height in px (default 675)
 *
 * Secondary crop (chained after the first):
 *   data-crop-secondary-ratio      — aspect ratio for the card thumbnail
 *   data-crop-secondary-max-width  — max output width in px
 *   data-crop-secondary-max-height — max output height in px
 *   The secondary result is injected into the `.field-image-crop-secondary` field.
 */
(function () {
    'use strict';

    var DEFAULT_RATIO = 16 / 9;
    var DEFAULT_MAX_WIDTH = 1200;
    var DEFAULT_MAX_HEIGHT = 675;
    var WEBP_QUALITY = 0.85;
    var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
    var ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    var cropper = null;
    var currentOriginalFile = null;

    // ── Config helpers ──

    function parseRatio(raw) {
        if (!raw) return DEFAULT_RATIO;
        if (raw === 'free') return NaN;
        var parts = raw.split('/');
        if (parts.length === 2) {
            var w = parseFloat(parts[0]);
            var h = parseFloat(parts[1]);
            if (w > 0 && h > 0) return w / h;
        }
        return DEFAULT_RATIO;
    }

    function buildRatioLabel(raw) {
        if (raw === 'free') return 'libre';
        return raw ? raw.replace('/', ':') : '16:9';
    }

    function readConfig(wrapper) {
        var ratioRaw = wrapper.getAttribute('data-crop-ratio');
        var ratio = parseRatio(ratioRaw);
        var maxW = parseInt(wrapper.getAttribute('data-crop-max-width'), 10) || DEFAULT_MAX_WIDTH;
        var maxH = parseInt(wrapper.getAttribute('data-crop-max-height'), 10) || DEFAULT_MAX_HEIGHT;

        // Secondary crop config
        var secRatioRaw = wrapper.getAttribute('data-crop-secondary-ratio');
        var secondary = null;
        if (secRatioRaw) {
            secondary = {
                ratio: parseRatio(secRatioRaw),
                maxWidth: parseInt(wrapper.getAttribute('data-crop-secondary-max-width'), 10) || DEFAULT_MAX_WIDTH,
                maxHeight: parseInt(wrapper.getAttribute('data-crop-secondary-max-height'), 10) || DEFAULT_MAX_HEIGHT,
                ratioLabel: buildRatioLabel(secRatioRaw),
            };
        }

        return { ratio: ratio, maxWidth: maxW, maxHeight: maxH, ratioLabel: buildRatioLabel(ratioRaw), secondary: secondary };
    }

    // ── Helpers ──

    function createEl(tag, attrs, children) {
        var el = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                var val = attrs[key];
                if (key === 'className') el.className = val;
                else if (key === 'textContent') el.textContent = val;
                else if (key === 'innerHTML') el.innerHTML = val;
                else if (key.startsWith('on') && typeof val === 'function') el.addEventListener(key.slice(2).toLowerCase(), val);
                else el.setAttribute(key, val);
            });
        }
        if (children) {
            children.forEach(function (c) { if (c) el.appendChild(c); });
        }
        return el;
    }

    // ── Drop zone replacement ──

    function initField(wrapper) {
        // wrapper = the .field-image-crop row_attr div (form-group level)
        var fileInput = wrapper.querySelector('input[type="file"]');
        if (!fileInput || fileInput.dataset.cropInit) return;
        fileInput.dataset.cropInit = '1';

        var config = readConfig(wrapper);

        // EasyAdmin's VichImage widget
        var eaVichImage = wrapper.querySelector('.ea-vich-image');
        if (!eaVichImage) return;

        // Create drop zone
        var dropZone = createEl('div', { className: 'crop-dropzone' }, [
            createEl('div', { className: 'crop-dropzone__inner' }, [
                createEl('div', { className: 'crop-dropzone__icon', innerHTML: '<i class="fa fa-cloud-upload-alt"></i>' }),
                createEl('div', { className: 'crop-dropzone__text', textContent: 'Glissez une image ici ou cliquez pour choisir' }),
                createEl('div', { className: 'crop-dropzone__hint', textContent: 'JPEG, PNG ou WebP — 10 Mo max — recadrage ' + config.ratioLabel }),
            ]),
        ]);

        // Preview container — aspect-ratio set dynamically
        var previewImg = createEl('img', { className: 'crop-preview__img' });
        if (!isNaN(config.ratio)) {
            previewImg.style.aspectRatio = config.ratio.toString();
        }

        var preview = createEl('div', { className: 'crop-preview', style: 'display:none' }, [
            previewImg,
            createEl('div', { className: 'crop-preview__actions' }, [
                createEl('button', {
                    type: 'button',
                    className: 'crop-preview__btn crop-preview__btn--change',
                    innerHTML: '<i class="fa fa-exchange-alt"></i> Changer',
                }),
                createEl('button', {
                    type: 'button',
                    className: 'crop-preview__btn crop-preview__btn--remove',
                    innerHTML: '<i class="fa fa-trash"></i> Supprimer',
                }),
            ]),
        ]);

        // Insert before the ea-vich-image widget
        eaVichImage.parentNode.insertBefore(dropZone, eaVichImage);
        eaVichImage.parentNode.insertBefore(preview, eaVichImage);

        // Hide original EasyAdmin widget (keep in DOM for form submission)
        eaVichImage.style.display = 'none';

        // Show existing image if present (EasyAdmin renders it as .ea-lightbox-thumbnail img)
        var existingImg = eaVichImage.querySelector('.ea-lightbox-thumbnail img');
        if (existingImg && existingImg.src) {
            showPreview(preview, dropZone, existingImg.src);
        }

        // ── Events ──

        dropZone.addEventListener('click', function () { fileInput.click(); });

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('crop-dropzone--active');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('crop-dropzone--active');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('crop-dropzone--active');
            if (e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0], fileInput, preview, dropZone, eaVichImage, config);
            }
        });

        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                handleFile(fileInput.files[0], fileInput, preview, dropZone, eaVichImage, config);
            }
        });

        // Change button
        preview.querySelector('.crop-preview__btn--change').addEventListener('click', function () {
            fileInput.click();
        });

        // Remove button
        preview.querySelector('.crop-preview__btn--remove').addEventListener('click', function () {
            hidePreview(preview, dropZone);
            // Check the VichUploader "delete" checkbox if available
            var deleteCheckbox = eaVichImage.querySelector('input[type="checkbox"]');
            if (deleteCheckbox) {
                deleteCheckbox.checked = true;
            }
            // Clear file input
            fileInput.value = '';
            // Also clear secondary field if present
            clearSecondaryField();
        });
    }

    function showPreview(previewEl, dropZone, src) {
        previewEl.querySelector('.crop-preview__img').src = src;
        previewEl.style.display = '';
        dropZone.style.display = 'none';
    }

    function hidePreview(previewEl, dropZone) {
        previewEl.style.display = 'none';
        dropZone.style.display = '';
    }

    function clearSecondaryField() {
        var secondaryWrapper = document.querySelector('.field-image-crop-secondary');
        if (!secondaryWrapper) return;
        var secInput = secondaryWrapper.querySelector('input[type="file"]');
        if (secInput) secInput.value = '';
        var secCheckbox = secondaryWrapper.querySelector('input[type="checkbox"]');
        if (secCheckbox) secCheckbox.checked = true;
    }

    // ── File handling ──

    function handleFile(file, fileInput, preview, dropZone, eaVichImage, config) {
        if (ACCEPTED_TYPES.indexOf(file.type) === -1) {
            alert('Format non supporté. Utilisez JPEG, PNG ou WebP.');
            return;
        }
        if (file.size > MAX_FILE_SIZE) {
            alert('Fichier trop volumineux (max 10 Mo).');
            return;
        }

        currentOriginalFile = file;

        // Primary crop callback
        var onConfirm = function (blob) {
            var webpFile = new File([blob], 'image.webp', { type: 'image/webp' });
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(webpFile);
            fileInput.files = dataTransfer.files;

            var deleteCheckbox = eaVichImage.querySelector('input[type="checkbox"]');
            if (deleteCheckbox) deleteCheckbox.checked = false;

            showPreview(preview, dropZone, URL.createObjectURL(blob));

            // Chain secondary crop if configured
            if (config.secondary && currentOriginalFile) {
                openSecondaryCrop(config.secondary, currentOriginalFile);
            }
        };

        openCropModal(file, 'Recadrer l\'image (' + config.ratioLabel + ')', config, 'Valider le cadrage', onConfirm);
    }

    // ── Secondary crop ──

    function openSecondaryCrop(secondaryConfig, originalFile) {
        var secondaryWrapper = document.querySelector('.field-image-crop-secondary');
        if (!secondaryWrapper) return;

        var secondaryFileInput = secondaryWrapper.querySelector('input[type="file"]');
        if (!secondaryFileInput) return;

        var secondaryEaVichImage = secondaryWrapper.querySelector('.ea-vich-image');

        var cropConfig = {
            ratio: secondaryConfig.ratio,
            maxWidth: secondaryConfig.maxWidth,
            maxHeight: secondaryConfig.maxHeight,
            ratioLabel: secondaryConfig.ratioLabel,
        };

        var onConfirm = function (blob) {
            var webpFile = new File([blob], 'image-card.webp', { type: 'image/webp' });
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(webpFile);
            secondaryFileInput.files = dataTransfer.files;

            if (secondaryEaVichImage) {
                var deleteCheckbox = secondaryEaVichImage.querySelector('input[type="checkbox"]');
                if (deleteCheckbox) deleteCheckbox.checked = false;
            }
        };

        openCropModal(originalFile, 'Recadrer la vignette (' + cropConfig.ratioLabel + ')', cropConfig, 'Valider la vignette', onConfirm);
    }

    // ── Generic crop modal ──

    function openCropModal(file, title, config, confirmLabel, onConfirm) {
        var overlay = createEl('div', { className: 'crop-modal-overlay' });
        var dialog = createEl('div', { className: 'crop-modal' });

        var header = createEl('div', { className: 'crop-modal__header' }, [
            createEl('h3', { className: 'crop-modal__title', textContent: title }),
            createEl('button', {
                type: 'button',
                className: 'crop-modal__close',
                innerHTML: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
                onClick: function () { closeCropModal(overlay); },
            }),
        ]);

        var body = createEl('div', { className: 'crop-modal__body' });
        var imgEl = createEl('img', { className: 'crop-modal__image', style: 'max-width:100%;display:block;' });
        body.appendChild(imgEl);

        var footer = createEl('div', { className: 'crop-modal__footer' }, [
            createEl('div', { className: 'crop-modal__tools' }, [
                createEl('button', {
                    type: 'button', className: 'crop-modal__tool', title: 'Rotation gauche',
                    innerHTML: '<i class="fa fa-undo"></i>',
                    onClick: function () { if (cropper) cropper.rotate(-90); },
                }),
                createEl('button', {
                    type: 'button', className: 'crop-modal__tool', title: 'Rotation droite',
                    innerHTML: '<i class="fa fa-redo"></i>',
                    onClick: function () { if (cropper) cropper.rotate(90); },
                }),
                createEl('button', {
                    type: 'button', className: 'crop-modal__tool', title: 'Retourner horizontal',
                    innerHTML: '<i class="fa fa-arrows-alt-h"></i>',
                    onClick: function () {
                        if (cropper) {
                            var d = cropper.getData();
                            cropper.scaleX(d.scaleX === -1 ? 1 : -1);
                        }
                    },
                }),
                createEl('button', {
                    type: 'button', className: 'crop-modal__tool', title: 'Réinitialiser',
                    innerHTML: '<i class="fa fa-sync-alt"></i>',
                    onClick: function () { if (cropper) cropper.reset(); },
                }),
            ]),
            createEl('div', { className: 'crop-modal__actions' }, [
                createEl('button', {
                    type: 'button', className: 'crop-modal__btn crop-modal__btn--cancel',
                    textContent: 'Annuler',
                    onClick: function () { closeCropModal(overlay); },
                }),
                createEl('button', {
                    type: 'button', className: 'crop-modal__btn crop-modal__btn--confirm',
                    textContent: confirmLabel,
                    onClick: function () { confirmCrop(overlay, config, onConfirm); },
                }),
            ]),
        ]);

        dialog.appendChild(header);
        dialog.appendChild(body);
        dialog.appendChild(footer);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Load image into cropper
        var reader = new FileReader();
        reader.onload = function (e) {
            imgEl.src = e.target.result;
            setTimeout(function () {
                cropper = new Cropper(imgEl, {
                    aspectRatio: config.ratio,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            }, 50);
        };
        reader.readAsDataURL(file);

        // Close on Escape
        overlay._escHandler = function (e) {
            if (e.key === 'Escape') closeCropModal(overlay);
        };
        document.addEventListener('keydown', overlay._escHandler);
    }

    function closeCropModal(overlay) {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        document.removeEventListener('keydown', overlay._escHandler);
        document.body.style.overflow = '';
        overlay.remove();
    }

    function confirmCrop(overlay, config, onConfirm) {
        if (!cropper) return;

        var canvas = cropper.getCroppedCanvas({
            maxWidth: config.maxWidth,
            maxHeight: config.maxHeight,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        canvas.toBlob(
            function (blob) {
                if (!blob) {
                    alert('Erreur lors du recadrage.');
                    return;
                }

                closeCropModal(overlay);
                onConfirm(blob);
            },
            'image/webp',
            WEBP_QUALITY
        );
    }

    // ── Init ──

    function init() {
        document.querySelectorAll('.field-image-crop').forEach(initField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init on EasyAdmin dynamic content
    document.addEventListener('ea.collection.item-added', init);
})();
