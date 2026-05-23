
        const productCatalog = {}; ?>;
        let productModalElement = null;
        let productModal = null;

        function initializeProductModal() {
            if (!productModalElement) {
                productModalElement = document.getElementById('productModal');
            }

            if (!productModalElement || !window.bootstrap || !bootstrap.Modal) {
                return null;
            }

            if (!productModal) {
                productModal = bootstrap.Modal.getOrCreateInstance(productModalElement);
            }

            return productModal;
        }

        function syncProductModalLayout() {
            productModalElement = productModalElement || document.getElementById('productModal');
            if (!productModalElement) {
                return;
            }

            const dialog = productModalElement.querySelector('.modal-dialog');
            const content = productModalElement.querySelector('.modal-content');
            const form = productModalElement.querySelector('.admin-product-form');
            const header = productModalElement.querySelector('.modal-header');
            const body = productModalElement.querySelector('.modal-body');
            const footer = productModalElement.querySelector('.modal-footer');

            if (!dialog || !content || !form || !header || !body || !footer) {
                return;
            }

            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 900;
            const modalHeight = Math.max(420, viewportHeight - 32);
            const chromeHeight = header.offsetHeight + footer.offsetHeight;
            const bodyHeight = Math.max(180, modalHeight - chromeHeight - 4);

            dialog.style.height = `${modalHeight}px`;
            dialog.style.maxHeight = `${modalHeight}px`;
            content.style.height = `${modalHeight}px`;
            content.style.maxHeight = `${modalHeight}px`;
            form.style.height = `${modalHeight - header.offsetHeight}px`;
            form.style.maxHeight = `${modalHeight - header.offsetHeight}px`;
            body.style.maxHeight = `${bodyHeight}px`;
            body.style.overflowY = 'auto';
        }

        function resetProductForm() {
            const form = document.getElementById('productForm');
            if (!form) {
                return;
            }

            form.reset();
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('productId').value = '';
            document.getElementById('productSubmitButton').textContent = 'Save Product';

            const currentImageBlock = document.getElementById('currentImageBlock');
            const currentImagePreview = document.getElementById('currentImagePreview');
            const currentImageName = document.getElementById('currentImageName');
            const removeProductImage = document.getElementById('removeProductImage');

            if (currentImageBlock) {
                currentImageBlock.style.display = 'none';
            }
            if (currentImagePreview) {
                currentImagePreview.src = '';
            }
            if (currentImageName) {
                currentImageName.textContent = '';
            }
            if (removeProductImage) {
                removeProductImage.checked = false;
            }

            [
                ['block' => 'arModel2dCurrentBlock', 'name' => 'arModel2dCurrentName', 'checkbox' => 'removeArModel2d'],
                ['block' => 'arModel2dLeftCurrentBlock', 'name' => 'arModel2dLeftCurrentName', 'checkbox' => 'removeArModel2dLeft'],
                ['block' => 'arModel2dRightCurrentBlock', 'name' => 'arModel2dRightCurrentName', 'checkbox' => 'removeArModel2dRight'],
                ['block' => 'arModel3dCurrentBlock', 'name' => 'arModel3dCurrentName', 'checkbox' => 'removeArModel3d'],
            ].forEach((entry) => {
                const block = document.getElementById(entry.block);
                const name = document.getElementById(entry.name);
                const checkbox = document.getElementById(entry.checkbox);

                if (block) {
                    block.style.display = 'none';
                }
                if (name) {
                    name.textContent = '';
                }
                if (checkbox) {
                    checkbox.checked = false;
                }
            });

            const arPositionX = document.getElementById('arPositionX');
            const arPositionY = document.getElementById('arPositionY');
            const arScale = document.getElementById('arScale');
            if (arPositionX) {
                arPositionX.value = '0';
            }
            if (arPositionY) {
                arPositionY.value = '0';
            }
            if (arScale) {
                arScale.value = '1';
            }

            for (let slot = 1; slot <= 4; slot++) {
                const previewBlock = document.getElementById(`galleryPreviewBlock${slot}`);
                const previewImage = document.getElementById(`galleryPreviewImage${slot}`);
                const previewName = document.getElementById(`galleryPreviewName${slot}`);
                const removeCheckbox = document.getElementById(`removeGalleryImage${slot}`);

                if (previewBlock) {
                    previewBlock.style.display = 'none';
                }
                if (previewImage) {
                    previewImage.src = '';
                }
                if (previewName) {
                    previewName.textContent = '';
                }
                if (removeCheckbox) {
                    removeCheckbox.checked = false;
                }
            }
        }

        function editProduct(productId) {
            const product = productCatalog[String(productId)] || productCatalog[productId];
            if (!product) {
                return;
            }

            resetProductForm();

            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productId').value = product.id || '';
            document.getElementById('productSubmitButton').textContent = 'Update Product';
            document.getElementById('productName').value = product.name || '';
            document.getElementById('productBrand').value = product.brand || '';
            document.getElementById('productCategory').value = product.category || '';
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productPrice').value = product.price || '';
            document.getElementById('productStock').value = product.stock || 0;

            const productIsFeatured = document.getElementById('productIsFeatured');
            if (productIsFeatured) {
                productIsFeatured.checked = Number(product.is_featured || 0) === 1;
            }

            const productMaterial = document.getElementById('productMaterial');
            if (productMaterial) {
                productMaterial.value = product.material || '';
            }

            const productColor = document.getElementById('productColor');
            if (productColor) {
                productColor.value = product.color || '';
            }

            const arPositionX = document.getElementById('arPositionX');
            const arPositionY = document.getElementById('arPositionY');
            const arScale = document.getElementById('arScale');
            if (arPositionX) {
                arPositionX.value = product.ar_position_x || '0';
            }
            if (arPositionY) {
                arPositionY.value = product.ar_position_y || '0';
            }
            if (arScale) {
                arScale.value = product.ar_scale || '1';
            }

            const currentImageBlock = document.getElementById('currentImageBlock');
            const currentImagePreview = document.getElementById('currentImagePreview');
            const currentImageName = document.getElementById('currentImageName');
            const removeProductImage = document.getElementById('removeProductImage');

            if (currentImageBlock && product.image_url && product.image) {
                currentImageBlock.style.display = 'block';
                currentImagePreview.src = product.image_url;
                currentImageName.textContent = product.image;
                if (removeProductImage) {
                    removeProductImage.checked = false;
                }
            }

            [
                ['urlKey' => 'ar_model_2d_url', 'nameKey' => 'ar_model_2d', 'block' => 'arModel2dCurrentBlock', 'name' => 'arModel2dCurrentName'],
                ['urlKey' => 'ar_model_2d_left_url', 'nameKey' => 'ar_model_2d_left', 'block' => 'arModel2dLeftCurrentBlock', 'name' => 'arModel2dLeftCurrentName'],
                ['urlKey' => 'ar_model_2d_right_url', 'nameKey' => 'ar_model_2d_right', 'block' => 'arModel2dRightCurrentBlock', 'name' => 'arModel2dRightCurrentName'],
                ['urlKey' => 'ar_model_3d', 'nameKey' => 'ar_model_3d', 'block' => 'arModel3dCurrentBlock', 'name' => 'arModel3dCurrentName'],
            ].forEach((entry) => {
                const block = document.getElementById(entry.block);
                const name = document.getElementById(entry.name);
                const assetName = product[entry.nameKey] || '';
                const assetUrl = product[entry.urlKey] || '';

                if (block && (assetName || assetUrl)) {
                    block.style.display = 'block';
                }
                if (name && assetName) {
                    name.textContent = assetName;
                }
            });

            const galleryImages = product.gallery_images || {};
            for (let slot = 1; slot <= 4; slot++) {
                const galleryItem = galleryImages[String(slot)] || galleryImages[slot];
                const previewBlock = document.getElementById(`galleryPreviewBlock${slot}`);
                const previewImage = document.getElementById(`galleryPreviewImage${slot}`);
                const previewName = document.getElementById(`galleryPreviewName${slot}`);
                const removeCheckbox = document.getElementById(`removeGalleryImage${slot}`);

                if (previewBlock && galleryItem && galleryItem.url) {
                    previewBlock.style.display = 'block';
                    if (previewImage) {
                        previewImage.src = galleryItem.url;
                    }
                    if (previewName) {
                        previewName.textContent = galleryItem.path || `Gallery image ${slot}`;
                    }
                    if (removeCheckbox) {
                        removeCheckbox.checked = false;
                    }
                }
            }

            const modalInstance = initializeProductModal();
            if (modalInstance) {
                modalInstance.show();
                setTimeout(syncProductModalLayout, 50);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            productModalElement = document.getElementById('productModal');
            initializeProductModal();
            syncProductModalLayout();

            document.getElementById('addProductButton')?.addEventListener('click', resetProductForm);
            productModalElement?.addEventListener('hidden.bs.modal', resetProductForm);
            productModalElement?.addEventListener('shown.bs.modal', syncProductModalLayout);
            window.addEventListener('resize', syncProductModalLayout);

            document.querySelectorAll('[data-edit-product]').forEach((button) => {
                button.addEventListener('click', function () {
                    editProduct(Number(this.dataset.editProduct || 0));
                });
            });

            document.querySelectorAll('[data-delete-product]').forEach((link) => {
                link.addEventListener('click', function (event) {
                    if (!confirmDelete('Delete this product?')) {
                        event.preventDefault();
                    }
                });
            });
        });
        
