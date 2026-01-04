/**
 * ============================================
 * SECURED CultureConnect - Create Post
 * Where interactions become emotions
 * Psychologically optimized for completion
 * ============================================
 * Security Fixes:
 * - Server-side Draft Persistence (Database)
 * - CSRF Protection integration
 * - Client-side file validation
 * - XSS prevention in all user inputs
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        titleMinLength: 10,
        titleMaxLength: 255,
        contentMinLength: 50,
        contentMaxLength: 5000,
        maxTags: 10,
        maxTagLength: 50,
        maxImageSize: 5 * 1024 * 1024, // 5MB
        allowedImageTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        allowedImageMimes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        autoSaveInterval: 30000, // 30 seconds
    };

    // State management
    const state = {
        hasUnsavedChanges: false,
        selectedImage: null,
        tags: [],
        formData: {},
        progressPercent: 0,
        draftId: null // Added for server-side autosave tracking
    };

    // DOM Elements
    const elements = {
        form: null,
        imageUploadZone: null,
        imageInput: null,
        imagePreview: null,
        uploadPlaceholder: null,
        removeImageBtn: null,
        titleInput: null,
        titleCount: null,
        contentInput: null,
        contentCount: null,
        contentHint: null,
        categoryInputs: null,
        tagInput: null,
        tagsDisplay: null,
        previewBtn: null,
        publishBtn: null,
        previewModal: null,
        previewContent: null,
        progressBar: null,
        progressFill: null
    };

    // ============================================
    // INITIALIZATION
    // ============================================

    document.addEventListener('DOMContentLoaded', () => {
        initializeElements();
        initializeImageUpload();
        initializeFormInputs();
        initializeTagsInput();
        initializePreview();
        initializeAutoSave();
        initializeProgressTracking();
        initializeUnsavedChangesWarning();
        
        console.log('✨ Create Post: The canvas awaits your story');
    });

    function initializeElements() {
        elements.form = document.getElementById('createPostForm');
        elements.imageUploadZone = document.getElementById('imageUploadZone');
        elements.imageInput = document.getElementById('mediaInput');
        elements.imagePreview = document.getElementById('imagePreview');
        elements.uploadPlaceholder = document.getElementById('uploadPlaceholder');
        elements.removeImageBtn = document.getElementById('removeImageBtn');
        elements.titleInput = document.getElementById('title');
        elements.titleCount = document.getElementById('titleCount');
        elements.contentInput = document.getElementById('content');
        elements.contentCount = document.getElementById('contentCount');
        elements.contentHint = document.getElementById('contentHint');
        elements.categoryInputs = document.querySelectorAll('input[name="category"]');
        elements.tagInput = document.getElementById('tagInput');
        elements.tagsDisplay = document.getElementById('tagsDisplay');
        elements.previewBtn = document.getElementById('previewBtn');
        elements.publishBtn = document.getElementById('publishBtn');
        elements.previewModal = document.getElementById('previewModal');
        elements.previewContent = document.getElementById('previewContent');
        elements.progressBar = document.querySelector('.progress-bar');
        elements.progressFill = document.querySelector('.progress-fill');
    }

    // ============================================
    // IMAGE UPLOAD
    // ============================================

    function initializeImageUpload() {
        if (!elements.imageUploadZone || !elements.imageInput) return;

        // Click to upload
        elements.imageUploadZone.addEventListener('click', (e) => {
            if (e.target === elements.removeImageBtn || elements.removeImageBtn.contains(e.target)) {
                return;
            }
            elements.imageInput.click();
        });

        // File input change
        elements.imageInput.addEventListener('change', handleImageSelect);

        // Drag and drop
        elements.imageUploadZone.addEventListener('dragover', handleDragOver);
        elements.imageUploadZone.addEventListener('dragleave', handleDragLeave);
        elements.imageUploadZone.addEventListener('drop', handleDrop);

        // Remove image
        if (elements.removeImageBtn) {
            elements.removeImageBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeImage();
            });
        }
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.imageUploadZone.classList.add('dragging');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.imageUploadZone.classList.remove('dragging');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.imageUploadZone.classList.remove('dragging');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleImageFile(files[0]);
        }
    }

    function handleImageSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            handleImageFile(files[0]);
        }
    }

    function handleImageFile(file) {
        // Strict file validation
        if (!CONFIG.allowedImageMimes.includes(file.type)) {
            showNotification('Please select a valid image file (JPEG, PNG, GIF, WebP)', 'error');
            elements.imageInput.value = ''; // Clear input
            return;
        }

        // Validate file size
        if (file.size > CONFIG.maxImageSize) {
            showNotification('Image must be less than 5MB', 'error');
            elements.imageInput.value = '';
            return;
        }

        // Validate file extension matches MIME type
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(extension)) {
            showNotification('Invalid file extension', 'error');
            elements.imageInput.value = '';
            return;
        }

        // Preview image
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                displayImagePreview(e.target.result);
                state.selectedImage = file;
                state.hasUnsavedChanges = true;
                updateProgress();
                
                // Animate upload success
                createSparkleEffect(elements.imageUploadZone);
                showNotification('✨ Image added! Your story is coming to life', 'success');
            };
            img.onerror = () => {
                showNotification('Invalid image file', 'error');
                elements.imageInput.value = '';
            };
            img.src = e.target.result;
        };
        reader.onerror = () => {
            showNotification('Failed to read file', 'error');
            elements.imageInput.value = '';
        };
        reader.readAsDataURL(file);
    }

    function displayImagePreview(imageSrc) {
        const img = elements.imagePreview.querySelector('img') || document.createElement('img');
        img.src = imageSrc;
        img.alt = 'Preview';
        
        if (!elements.imagePreview.querySelector('img')) {
            elements.imagePreview.insertBefore(img, elements.removeImageBtn);
        }

        elements.uploadPlaceholder.style.display = 'none';
        elements.imagePreview.style.display = 'block';
        
        // Animate in
        img.style.animation = 'imageZoomIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
    }

    function removeImage() {
        elements.imageInput.value = '';
        elements.imagePreview.style.display = 'none';
        elements.uploadPlaceholder.style.display = 'block';
        state.selectedImage = null;
        state.hasUnsavedChanges = true;
        updateProgress();
        
        showNotification('Image removed', 'info');
    }

    // ============================================
    // FORM INPUTS
    // ============================================

    function initializeFormInputs() {
        // Title character counter
        if (elements.titleInput && elements.titleCount) {
            // Initialize count
            elements.titleCount.textContent = elements.titleInput.value.length;
            
            elements.titleInput.addEventListener('input', () => {
                const length = elements.titleInput.value.length;
                elements.titleCount.textContent = length;
                
                if (length > CONFIG.titleMaxLength - 20) {
                    elements.titleCount.classList.add('warning');
                } else {
                    elements.titleCount.classList.remove('warning');
                }
                
                if (length >= CONFIG.titleMaxLength) {
                    elements.titleCount.classList.add('error');
                } else {
                    elements.titleCount.classList.remove('error');
                }
                
                state.hasUnsavedChanges = true;
                updateProgress();
            });
        }

        // Content character counter with progress hints
        if (elements.contentInput && elements.contentCount) {
            // Initialize count
            elements.contentCount.textContent = elements.contentInput.value.length;
            
            elements.contentInput.addEventListener('input', () => {
                const length = elements.contentInput.value.length;
                elements.contentCount.textContent = length;
                
                // Update hint based on progress
                if (length < CONFIG.contentMinLength) {
                    elements.contentHint.textContent = `${CONFIG.contentMinLength - length} more characters needed`;
                    elements.contentHint.style.color = 'var(--warning)';
                } else if (length < CONFIG.contentMinLength + 50) {
                    elements.contentHint.textContent = 'Perfect! Keep the magic flowing ✨';
                    elements.contentHint.style.color = 'var(--success)';
                } else {
                    elements.contentHint.textContent = 'Your story is resonating beautifully 💫';
                    elements.contentHint.style.color = 'var(--primary)';
                }
                
                if (length > CONFIG.contentMaxLength - 200) {
                    elements.contentCount.classList.add('warning');
                } else {
                    elements.contentCount.classList.remove('warning');
                }
                
                if (length >= CONFIG.contentMaxLength) {
                    elements.contentCount.classList.add('error');
                } else {
                    elements.contentCount.classList.remove('error');
                }
                
                state.hasUnsavedChanges = true;
                updateProgress();
            });
        }

        // Category selection animation
        if (elements.categoryInputs) {
            elements.categoryInputs.forEach(input => {
                input.addEventListener('change', () => {
                    createPulseEffect(input.parentElement);
                    state.hasUnsavedChanges = true;
                    updateProgress();
                });
            });
        }

        // Form field focus effects
        document.querySelectorAll('.form-input, .form-textarea').forEach(field => {
            field.addEventListener('focus', function() {
                createFocusRipple(this);
            });
        });
    }

    // ============================================
    // TAGS INPUT
    // ============================================

    function initializeTagsInput() {
        if (!elements.tagInput || !elements.tagsDisplay) return;

        elements.tagInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTag(elements.tagInput.value.trim());
                elements.tagInput.value = '';
            }
        });

        elements.tagInput.addEventListener('blur', () => {
            const value = elements.tagInput.value.trim();
            if (value) {
                addTag(value);
                elements.tagInput.value = '';
            }
        });
    }

    function addTag(tagText) {
        tagText = tagText.trim();
        
        if (!tagText) return;
        
        if (tagText.length > CONFIG.maxTagLength) {
            showNotification(`Tag must be ${CONFIG.maxTagLength} characters or less`, 'warning');
            return;
        }
        
        if (containsSuspiciousContent(tagText)) {
            showNotification('Tag contains invalid characters', 'error');
            return;
        }
        
        if (state.tags.length >= CONFIG.maxTags) {
            showNotification(`Maximum ${CONFIG.maxTags} tags allowed`, 'warning');
            return;
        }
        
        if (state.tags.includes(tagText)) {
            showNotification('Tag already added', 'info');
            return;
        }
        
        state.tags.push(tagText);
        renderTags();
        state.hasUnsavedChanges = true;
        updateProgress();
        
        // Animate tag addition
        const newTag = elements.tagsDisplay.lastElementChild;
        if (newTag) {
            createSparkleEffect(newTag);
        }
    }

    function removeTag(index) {
        state.tags.splice(index, 1);
        renderTags();
        state.hasUnsavedChanges = true;
        updateProgress();
    }

    function renderTags() {
        elements.tagsDisplay.innerHTML = '';
        
        state.tags.forEach((tag, index) => {
            const tagElement = document.createElement('div');
            tagElement.className = 'tag-item';
            
            const tagSpan = document.createElement('span');
            tagSpan.textContent = tag;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'tag-remove';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeTag(index);
            
            tagElement.appendChild(tagSpan);
            tagElement.appendChild(removeBtn);
            elements.tagsDisplay.appendChild(tagElement);
        });
        
        // Update hidden input for form submission
        updateTagsHiddenInput();
    }

    function updateTagsHiddenInput() {
        const existing = elements.form.querySelector('input[name="tags"]');
        if (existing) {
            existing.remove();
        }
        
        if (state.tags.length > 0) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'tags';
            hiddenInput.value = JSON.stringify(state.tags);
            elements.form.appendChild(hiddenInput);
        }
    }

    window.removeTag = removeTag;

    // ============================================
    // PREVIEW
    // ============================================

    function initializePreview() {
        if (!elements.previewBtn || !elements.previewModal) return;

        elements.previewBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showPreview();
        });
    }

    function showPreview() {
        const title = elements.titleInput.value;
        const content = elements.contentInput.value;
        const category = document.querySelector('input[name="category"]:checked');
        const country = document.querySelector('input[name="country"]')?.value || '';

        if (!title || !content || !category) {
            showNotification('Please fill in title, content, and category before previewing', 'warning');
            return;
        }

        const previewDiv = document.createElement('div');
        previewDiv.className = 'preview-post';
        
        if (state.selectedImage && elements.imagePreview.querySelector('img')) {
            const imageDiv = document.createElement('div');
            imageDiv.className = 'preview-image';
            const img = document.createElement('img');
            img.src = elements.imagePreview.querySelector('img').src;
            img.alt = 'Preview';
            imageDiv.appendChild(img);
            previewDiv.appendChild(imageDiv);
        }
        
        const headerDiv = document.createElement('div');
        headerDiv.className = 'preview-header';
        
        const categorySpan = document.createElement('span');
        categorySpan.className = 'preview-category';
        categorySpan.textContent = category.value;
        headerDiv.appendChild(categorySpan);
        
        if (country) {
            const countrySpan = document.createElement('span');
            countrySpan.className = 'preview-country';
            countrySpan.textContent = '🌍 ' + country;
            headerDiv.appendChild(countrySpan);
        }
        previewDiv.appendChild(headerDiv);
        
        const titleH2 = document.createElement('h2');
        titleH2.className = 'preview-title';
        titleH2.textContent = title;
        previewDiv.appendChild(titleH2);
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'preview-content';
        contentDiv.textContent = content;
        contentDiv.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
        previewDiv.appendChild(contentDiv);
        
        if (state.tags.length > 0) {
            const tagsDiv = document.createElement('div');
            tagsDiv.className = 'preview-tags';
            state.tags.forEach(tag => {
                const tagSpan = document.createElement('span');
                tagSpan.className = 'preview-tag';
                tagSpan.textContent = tag;
                tagsDiv.appendChild(tagSpan);
            });
            previewDiv.appendChild(tagsDiv);
        }
        
        const footerDiv = document.createElement('div');
        footerDiv.className = 'preview-footer';
        const footerP = document.createElement('p');
        footerP.textContent = '👁️ This is how your story will appear to the world';
        footerDiv.appendChild(footerP);
        previewDiv.appendChild(footerDiv);

        elements.previewContent.innerHTML = '';
        elements.previewContent.appendChild(previewDiv);
        elements.previewModal.style.display = 'flex';
        
        elements.previewModal.style.animation = 'fadeIn 0.3s ease';
    }

    window.closePreview = function() {
        elements.previewModal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            elements.previewModal.style.display = 'none';
        }, 300);
    };

    // ============================================
    // PROGRESS TRACKING
    // ============================================

    function initializeProgressTracking() {
        if (!elements.progressBar) return;
        updateProgress();
    }

    function updateProgress() {
        const checks = {
            hasImage: !!state.selectedImage,
            hasTitle: elements.titleInput?.value.length >= CONFIG.titleMinLength,
            hasContent: elements.contentInput?.value.length >= CONFIG.contentMinLength,
            hasCategory: !!document.querySelector('input[name="category"]:checked'),
            hasCountry: !!document.querySelector('input[name="country"]')?.value,
            hasTags: state.tags.length > 0
        };

        const completed = Object.values(checks).filter(Boolean).length;
        const total = Object.keys(checks).length;
        const percent = (completed / total) * 100;

        state.progressPercent = percent;
        elements.progressFill.style.width = `${percent}%`;

        if (percent < 33) {
            elements.progressFill.style.background = 'linear-gradient(90deg, #ef4444, #f59e0b)';
        } else if (percent < 66) {
            elements.progressFill.style.background = 'linear-gradient(90deg, #f59e0b, #10b981)';
        } else {
            elements.progressFill.style.background = 'linear-gradient(90deg, #10b981, #059669)';
        }

        if (elements.publishBtn) {
            const canPublish = checks.hasTitle && checks.hasContent && checks.hasCategory;
            elements.publishBtn.disabled = !canPublish;
            elements.publishBtn.style.opacity = canPublish ? '1' : '0.6';
            elements.publishBtn.style.cursor = canPublish ? 'pointer' : 'not-allowed';
        }
    }

    // ============================================
    // AUTO SAVE (SERVER-SIDE)
    // ============================================

    function initializeAutoSave() {
        setInterval(() => {
            if (state.hasUnsavedChanges) {
                autoSaveToLocalStorage(); // Saves to server
            }
        }, CONFIG.autoSaveInterval);

        // Load saved draft from database on page load
        loadDraftFromServer();
    }

    async function autoSaveToLocalStorage() {
        // 1. Gather Data
        const formData = new FormData();
        formData.append('title', elements.titleInput?.value || '');
        formData.append('content', elements.contentInput?.value || '');
        
        const category = document.querySelector('input[name="category"]:checked');
        if (category) formData.append('category', category.value);
        
        const country = document.querySelector('input[name="country"]');
        if (country) formData.append('country', country.value);

        if (state.tags.length > 0) {
            formData.append('tags', JSON.stringify(state.tags));
        }

        // 2. Include Draft ID (if we have one)
        if (state.draftId) {
            formData.append('draft_id', state.draftId);
        }

        // 3. Include CSRF Token
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.getAttribute('content'));
        }

        // 4. Send to Server
        try {
            const response = await fetch('autosave.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.draft_id) {
                state.draftId = data.draft_id; // Update ID for next save
                
                // Optional: Subtle log to console
                console.log(`☁️ Auto-saved at ${data.timestamp}`);
            }
        } catch (error) {
            console.error('Autosave silent fail:', error);
        }
    }

    // Replaces loadDraftFromLocalStorage
    async function loadDraftFromServer() {
        try {
            const response = await fetch('autosave.php'); // GET request
            const data = await response.json();

            if (data.success && data.draft) {
                const draft = data.draft;
                
                if (confirm(`📝 We found a saved draft from ${draft.updated_at || 'earlier'}. Would you like to restore it?`)) {
                    
                    // 1. Restore State ID
                    state.draftId = draft.id;

                    // 2. Restore Text Fields
                    if (elements.titleInput) elements.titleInput.value = draft.title || '';
                    if (elements.contentInput) elements.contentInput.value = draft.content || '';
                    
                    // 3. Restore Category
                    if (draft.category) {
                        const catInput = document.querySelector(`input[name="category"][value="${CSS.escape(draft.category)}"]`);
                        if (catInput) catInput.checked = true;
                    }
                    
                    // 4. Restore Country
                    if (draft.country && elements.form.querySelector('input[name="country"]')) {
                        elements.form.querySelector('input[name="country"]').value = draft.country;
                    }
                    
                    // 5. Restore Tags
                    if (draft.tags) {
                        try {
                            const savedTags = JSON.parse(draft.tags);
                            if (Array.isArray(savedTags)) {
                                state.tags = savedTags;
                                renderTags();
                            }
                        } catch (e) {
                            console.warn('Error parsing draft tags');
                        }
                    }

                    // 6. Update UI
                    updateProgress();
                    showNotification('✨ Draft restored successfully', 'success');
                }
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
        }
    }

    // ============================================
    // UNSAVED CHANGES WARNING
    // ============================================

    function initializeUnsavedChangesWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (state.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        if (elements.form) {
            elements.form.addEventListener('submit', () => {
                state.hasUnsavedChanges = false;
                // No longer needed to clear localStorage, but keeping logic clean
                state.draftId = null; 
            });
        }
    }

    // ============================================
    // SECURITY UTILITY FUNCTIONS
    // ============================================

    function containsSuspiciousContent(text) {
        const dangerousPatterns = [
            /<script[^>]*>.*?<\/script>/gi,
            /javascript:/gi,
            /on\w+\s*=/gi,
            /<iframe/gi,
            /<embed/gi,
            /<object/gi
        ];
        return dangerousPatterns.some(pattern => pattern.test(text));
    }

    // ============================================
    // VISUAL EFFECTS
    // ============================================

    function createFocusRipple(element) {
        const ripple = document.createElement('div');
        
        ripple.style.cssText = `
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(45, 137, 239, 0.3);
            border-radius: 50%;
            left: 10px;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: focusRipple 0.6s ease-out;
            pointer-events: none;
            z-index: 1;
        `;
        
        element.parentElement.style.position = 'relative';
        element.parentElement.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    }

    function createSparkleEffect(element) {
        const sparkles = ['✨', '💫', '⭐', '🌟'];
        const sparkle = document.createElement('div');
        
        sparkle.textContent = sparkles[Math.floor(Math.random() * sparkles.length)];
        sparkle.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            pointer-events: none;
            animation: sparkleFloat 1s ease-out forwards;
            z-index: 1000;
        `;
        
        element.style.position = 'relative';
        element.appendChild(sparkle);
        
        setTimeout(() => sparkle.remove(), 1000);
    }

    function createPulseEffect(element) {
        element.style.animation = 'none';
        setTimeout(() => {
            element.style.animation = 'pulse 0.5s ease';
        }, 10);
    }

    // ============================================
    // NOTIFICATIONS
    // ============================================

    function showNotification(message, type = 'info', duration = 3000) {
        document.querySelectorAll('.toast-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        
        const colors = {
            success: { bg: '#e6f9ed', border: '#10b981', text: '#1b7f3a' },
            error: { bg: '#ffeaea', border: '#ef4444', text: '#d32f2f' },
            warning: { bg: '#fff3cd', border: '#f59e0b', text: '#9a6e00' },
            info: { bg: '#e3f2fd', border: '#2D89EF', text: '#1A5FB4' }
        };
        
        const color = colors[type] || colors.info;
        
        notification.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            background: ${color.bg};
            border: 2px solid ${color.border};
            color: ${color.text};
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            font-weight: 500;
            z-index: 10000;
            animation: slideInUp 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 300px;
        `;
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutDown 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    console.log(
        '%c🌍 CultureConnect Create Post',
        'font-size: 20px; font-weight: bold; background: linear-gradient(135deg, #2D89EF 0%, #764BA2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;'
    );
    console.log(
        '%cEvery story you share plants a seed of connection 🌱',
        'font-size: 14px; color: #2D89EF; font-style: italic;'
    );

})();