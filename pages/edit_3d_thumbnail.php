<?php

// This partial is included by edit.php directly after the configured Title field.
$three_d_thumbnail_show_upload_fallback = $three_d_thumbnail_show_upload_fallback ?? true;
?>
<div class="Question" id="question_3d_thumbnail">
    <label for="create_3d_thumbnail"><?php echo escape($lang['3dthumbnail']); ?></label>
    <div class="stdwidth">
        <p class="FormHelpInner" id="three_d_thumbnail_status"><?php echo escape($lang['3dthumbnail-checking']); ?></p>
        <button type="button" id="create_3d_thumbnail" disabled>
            <i class="icon-box" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['3dthumbnail-create']); ?>
        </button>
        <?php if ($three_d_thumbnail_show_upload_fallback) { ?>
            <span class="ThreeDThumbnailOr"><?php echo escape($lang['or']); ?></span>
            <label class="ThreeDThumbnailUploadLabel" for="upload_3d_thumbnail">
                <?php echo escape($lang['3dthumbnail-upload']); ?>
                <input type="file" id="upload_3d_thumbnail" accept="image/jpeg,.jpg,.jpeg">
            </label>
        <?php } ?>
        <img id="three_d_thumbnail_preview" class="ThreeDThumbnailPreview" alt="<?php echo escape($lang['3dthumbnail']); ?>" style="display:none;">
    </div>
    <div class="clearerleft"></div>
</div>

<style>
    .ThreeDThumbnailOr { margin: 0 0.75rem; }
    .ThreeDThumbnailUploadLabel { display: inline-block; cursor: pointer; }
    .ThreeDThumbnailUploadLabel input { display: block; margin-top: 0.35rem; }
    .ThreeDThumbnailPreview { display: block; width: 180px; height: auto; margin-top: 0.75rem; border-radius: 4px; }
</style>

<script>
    (() => {
        const resourceRef = <?php echo (int) $ref; ?>;
        const extension = <?php echo json_encode(strtolower((string) $resource['file_extension'])); ?>;
        const modelUrl = <?php echo json_encode($three_d_thumbnail_model_url); ?>;
        const libraryUrl = <?php echo json_encode($baseurl_short . 'lib/three-r128/'); ?>;
        const uploadUrl = <?php echo json_encode($baseurl_short . 'pages/ajax/upload_3d_thumbnail.php'); ?>;
        const csrfIdentifier = <?php echo json_encode($CSRF_token_identifier); ?>;
        const csrfToken = <?php echo json_encode($three_d_thumbnail_csrf_token); ?>;
        const messages = {
            ready: <?php echo json_encode($lang['3dthumbnail-ready']); ?>,
            unavailable: <?php echo json_encode($lang['3dthumbnail-unavailable']); ?>,
            creating: <?php echo json_encode($lang['3dthumbnail-creating']); ?>,
            saving: <?php echo json_encode($lang['3dthumbnail-saving']); ?>,
            created: <?php echo json_encode($lang['3dthumbnail-created']); ?>,
            uploaded: <?php echo json_encode($lang['3dthumbnail-uploaded']); ?>,
            invalid: <?php echo json_encode($lang['3dthumbnail-invalid']); ?>,
            failed: <?php echo json_encode($lang['3dthumbnail-failed']); ?>
        };
        const createButton = document.getElementById('create_3d_thumbnail');
        const uploadInput = document.getElementById('upload_3d_thumbnail');
        const status = document.getElementById('three_d_thumbnail_status');
        const preview = document.getElementById('three_d_thumbnail_preview');
        let renderer = null;
        let scene = null;
        let camera = null;

        if (!createButton || !status || !preview) {
            return;
        }

        function setStatus(message, isError = false) {
            status.textContent = message;
            status.classList.toggle('FormError', isError);
        }

        function loadScript(src) {
            return new Promise((resolve, reject) => {
                const existing = document.querySelector(`script[data-rs-src="${src}"]`);
                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', () => resolve(), { once: true });
                    existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.dataset.rsSrc = src;
                script.addEventListener('load', () => {
                    script.dataset.loaded = 'true';
                    resolve();
                }, { once: true });
                script.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
                document.head.appendChild(script);
            });
        }

        function loadViewerLibraries() {
            if (window.ResourceSpaceThreeR128Ready) {
                return window.ResourceSpaceThreeR128Ready;
            }

            const libraries = [
                'three.min.js',
                'libs/fflate.min.js',
                'loaders/OBJLoader.js',
                'loaders/GLTFLoader.js',
                'loaders/FBXLoader.js'
            ];
            window.ResourceSpaceThreeR128Ready = libraries.reduce(
                (promise, library) => promise.then(() => loadScript(libraryUrl + library)),
                Promise.resolve()
            );
            return window.ResourceSpaceThreeR128Ready;
        }

        function frameObject(object) {
            const THREE = window.THREE;
            const box = new THREE.Box3().setFromObject(object);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());
            const maxDim = Math.max(size.x, size.y, size.z) || 1;
            const distance = maxDim / (2 * Math.tan((Math.PI * camera.fov) / 360));

            object.position.sub(center);
            camera.near = Math.max(maxDim / 100, 0.01);
            camera.far = Math.max(maxDim * 100, 1000);
            camera.position.set(distance * 1.2, distance * 0.8, distance * 1.2);
            camera.lookAt(0, 0, 0);
            camera.updateProjectionMatrix();
        }

        function loadModel() {
            return new Promise((resolve, reject) => {
                const onLoaded = (object) => resolve(object);
                if (extension === 'glb' || extension === 'gltf') {
                    new window.THREE.GLTFLoader().load(modelUrl, (gltf) => onLoaded(gltf.scene), undefined, reject);
                } else if (extension === 'obj') {
                    new window.THREE.OBJLoader().load(modelUrl, onLoaded, undefined, reject);
                } else if (extension === 'fbx') {
                    new window.THREE.FBXLoader().load(modelUrl, onLoaded, undefined, reject);
                } else {
                    reject(new Error('Unsupported 3D preview format'));
                }
            });
        }

        function uploadThumbnail(file, successMessage) {
            const formData = new FormData();
            formData.append('ref', resourceRef);
            formData.append(csrfIdentifier, csrfToken);
            formData.append('userfile', file, '3d-model-thumbnail.jpg');
            createButton.disabled = true;
            setStatus(messages.saving);

            jQuery.ajax({
                type: 'POST',
                url: uploadUrl,
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            })
                .done((response) => {
                    if (response.status !== 'success') {
                        setStatus(response.data && response.data.message ? response.data.message : messages.failed, true);
                        return;
                    }
                    preview.src = response.data.thumbnail_url;
                    preview.style.display = 'block';
                    setStatus(successMessage);
                })
                .fail((xhr) => {
                    const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : messages.failed;
                    setStatus(message, true);
                })
                .always(() => {
                    createButton.disabled = !renderer;
                });
        }

        createButton.addEventListener('click', () => {
            if (!renderer || !scene || !camera) {
                return;
            }
            createButton.disabled = true;
            setStatus(messages.creating);
            renderer.render(scene, camera);
            renderer.domElement.toBlob((blob) => {
                if (!blob) {
                    setStatus(messages.unavailable, true);
                    createButton.disabled = false;
                    return;
                }
                uploadThumbnail(blob, messages.created);
            }, 'image/jpeg', 0.92);
        });

        if (uploadInput) {
            uploadInput.addEventListener('change', () => {
                const file = uploadInput.files[0];
                if (!file) {
                    return;
                }
                if (!['image/jpeg', 'image/pjpeg'].includes(file.type) && !/\.jpe?g$/i.test(file.name)) {
                    setStatus(messages.invalid, true);
                    uploadInput.value = '';
                    return;
                }
                uploadThumbnail(file, messages.uploaded);
            });
        }

        loadViewerLibraries()
            .then(() => {
                const THREE = window.THREE;
                if (!THREE || !THREE.OBJLoader || !THREE.GLTFLoader || !THREE.FBXLoader) {
                    throw new Error('The local 3D viewer libraries did not initialise correctly');
                }
                THREE.Object3D.DefaultUp.set(0, 0, 1);
                renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
                renderer.setSize(960, 720, false);
                renderer.setClearColor(0x1b1b1b, 1);
                renderer.outputEncoding = THREE.sRGBEncoding;
                scene = new THREE.Scene();
                camera = new THREE.PerspectiveCamera(45, 4 / 3, 0.1, 5000);
                camera.up.set(0, 0, 1);
                scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 1.8));
                const keyLight = new THREE.DirectionalLight(0xffffff, 1.5);
                keyLight.position.set(5, 10, 7);
                scene.add(keyLight);
                const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
                fillLight.position.set(-5, 4, -6);
                scene.add(fillLight);
                const grid = new THREE.GridHelper(10, 10, 0x666666, 0x333333);
                grid.rotation.x = Math.PI / 2;
                scene.add(grid);
                return loadModel();
            })
            .then((model) => {
                scene.add(model);
                frameObject(model);
                renderer.render(scene, camera);
                createButton.disabled = false;
                setStatus(messages.ready);
            })
            .catch((error) => {
                console.warn('3D thumbnail generation is unavailable.', error);
                renderer = null;
                setStatus(messages.unavailable, true);
            });
    })();
</script>
