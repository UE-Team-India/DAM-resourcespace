<?php

$supported_3d_extensions = ['obj', 'fbx', 'gltf', 'glb'];
$resource_extension = strtolower((string) $resource['file_extension']);

if (
    !in_array($resource_extension, $supported_3d_extensions, true)
    || !resource_download_allowed($ref, '', $resource['resource_type'], -1, true)
) {
    return;
}

$model_url = get_resource_path(
    $ref,
    false,
    '',
    false,
    $resource['file_extension'],
    true,
    1,
    false,
    '',
    -1,
    true
);

$viewer_id = 'rs-3d-viewer-' . $ref;
$canvas_id = $viewer_id . '-canvas';
$status_id = $viewer_id . '-status';
$hdr_url = $baseurl_short . 'gfx/hdr/citrus_orchard_road_puresky_1k.hdr';
$three_library_url = $baseurl_short . 'lib/three-r128/';
?>
<div id="<?php echo escape($viewer_id); ?>" class="ResourceModel3DViewer">
    <canvas id="<?php echo escape($canvas_id); ?>" class="ResourceModel3DCanvas"></canvas>
    <div class="ResourceModel3DAxisHud">
        <canvas id="<?php echo escape($canvas_id . '-axes'); ?>" class="ResourceModel3DAxisCanvas" width="84" height="84"></canvas>
    </div>
    <div id="<?php echo escape($status_id); ?>" class="ResourceModel3DStatus">
        Loading 3D preview...
    </div>
</div>

<style>
    .ResourceModel3DViewer {
        position: relative;
        width: 100%;
        min-height: 560px;
        background: #111;
        border-radius: 8px;
        overflow: hidden;
    }

    .ResourceModel3DCanvas {
        width: 100%;
        height: 560px;
        display: block;
    }

    .ResourceModel3DStatus {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 2;
        padding: 8px 12px;
        background: rgba(0, 0, 0, 0.65);
        color: #fff;
        border-radius: 6px;
        font-size: 14px;
    }

    .ResourceModel3DAxisHud {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 84px;
        height: 84px;
        z-index: 2;
        pointer-events: none;
        background: rgba(0, 0, 0, 0.16);
        border-radius: 999px;
    }

    .ResourceModel3DAxisCanvas {
        width: 84px;
        height: 84px;
        display: block;
    }
</style>

<script>
    (() => {
        const extension = <?php echo json_encode($resource_extension); ?>;
        const modelUrl = <?php echo json_encode($model_url); ?>;
        const hdrUrl = <?php echo json_encode($hdr_url); ?>;
        const threeLibraryUrl = <?php echo json_encode($three_library_url); ?>;
        const canvas = document.getElementById(<?php echo json_encode($canvas_id); ?>);
        const axisCanvas = document.getElementById(<?php echo json_encode($canvas_id . '-axes'); ?>);
        const status = document.getElementById(<?php echo json_encode($status_id); ?>);

        if (!canvas || !axisCanvas || !status) {
            return;
        }

        function setStatus(message, isError = false) {
            status.textContent = message;
            status.style.background = isError ? 'rgba(153, 27, 27, 0.85)' : 'rgba(0, 0, 0, 0.65)';
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

        function onProgress(event) {
            if (!event.lengthComputable || event.total === 0) {
                setStatus('Loading 3D preview...');
                return;
            }

            const percentage = Math.round((event.loaded / event.total) * 100);
            setStatus(`Loading 3D preview... ${percentage}%`);
        }

        function onError(error) {
            console.error(error);
            setStatus('Unable to load 3D preview for this model.', true);
        }

        function loadViewerLibraries() {
            const libraries = [
                'three.min.js',
                'libs/fflate.min.js',
                'controls/OrbitControls.js',
                'loaders/RGBELoader.js',
                'loaders/OBJLoader.js',
                'loaders/GLTFLoader.js',
                'loaders/FBXLoader.js'
            ];

            const viewerReady = () => (
                window.THREE
                && window.THREE.OrbitControls
                && window.THREE.RGBELoader
                && window.THREE.OBJLoader
                && window.THREE.GLTFLoader
                && window.THREE.FBXLoader
            );

            if (viewerReady()) {
                return Promise.resolve();
            }

            // Other pages can preload a subset of the Three.js files. Always extend that
            // shared load, rather than assuming it contains the viewer's full dependency set.
            if (!window.ResourceSpaceThreeR128ViewerReady) {
                const existingLoad = window.ResourceSpaceThreeR128Ready;
                const waitForExistingLoad = (
                    existingLoad && typeof existingLoad.then === 'function'
                        ? existingLoad.catch(() => undefined)
                        : Promise.resolve()
                );

                window.ResourceSpaceThreeR128ViewerReady = waitForExistingLoad
                    .then(() => libraries.reduce(
                        (promise, library) => promise.then(() => loadScript(threeLibraryUrl + library)),
                        Promise.resolve()
                    ))
                    .then(() => {
                        if (!viewerReady()) {
                            throw new Error('The local 3D viewer libraries did not initialise correctly');
                        }
                    });
                window.ResourceSpaceThreeR128Ready = window.ResourceSpaceThreeR128ViewerReady;
            }

            return window.ResourceSpaceThreeR128ViewerReady;
        }

        loadViewerLibraries()
            .then(() => {
                const THREE = window.THREE;
                if (!THREE || !THREE.OrbitControls || !THREE.RGBELoader || !THREE.OBJLoader || !THREE.GLTFLoader || !THREE.FBXLoader) {
                    throw new Error('The local 3D viewer libraries did not initialise correctly');
                }

                THREE.Object3D.DefaultUp.set(0, 0, 1);

                const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
                renderer.setPixelRatio(window.devicePixelRatio || 1);
                renderer.outputEncoding = THREE.sRGBEncoding;

                const axisRenderer = new THREE.WebGLRenderer({ canvas: axisCanvas, antialias: true, alpha: true });
                axisRenderer.setPixelRatio(window.devicePixelRatio || 1);
                axisRenderer.setClearColor(0x000000, 0);

                const scene = new THREE.Scene();

                const axisScene = new THREE.Scene();

                const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
                camera.up.set(0, 0, 1);
                camera.position.set(2, 2, 4);

                const axisCamera = new THREE.PerspectiveCamera(50, 1, 0.1, 10);
                axisCamera.position.set(0, 0, 2.3);

                const controls = new THREE.OrbitControls(camera, canvas);
                controls.enableDamping = true;
                controls.target.set(0, 0, 0);

                const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1.5);
                scene.add(hemiLight);

                const dirLight = new THREE.DirectionalLight(0xffffff, 1.3);
                dirLight.position.set(5, 10, 7);
                scene.add(dirLight);

                const fillLight = new THREE.DirectionalLight(0xffffff, 0.8);
                fillLight.position.set(-5, 4, -6);
                scene.add(fillLight);

                const grid = new THREE.GridHelper(10, 10, 0x666666, 0x333333);
                grid.rotation.x = Math.PI / 2;
                scene.add(grid);

                const pmremGenerator = new THREE.PMREMGenerator(renderer);
                pmremGenerator.compileEquirectangularShader();

                const axisRoot = new THREE.Group();
                axisScene.add(axisRoot);

                function createArrowAxis(direction, color) {
                    const axisGroup = new THREE.Group();
                    const normalizedDirection = direction.clone().normalize();

                    const shaft = new THREE.Mesh(
                        new THREE.CylinderGeometry(0.03, 0.03, 0.56, 16),
                        new THREE.MeshBasicMaterial({ color })
                    );
                    shaft.position.copy(normalizedDirection.clone().multiplyScalar(0.28));
                    shaft.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), normalizedDirection);
                    axisGroup.add(shaft);

                    const head = new THREE.Mesh(
                        new THREE.ConeGeometry(0.08, 0.2, 20),
                        new THREE.MeshBasicMaterial({ color })
                    );
                    head.position.copy(normalizedDirection.clone().multiplyScalar(0.64));
                    head.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), normalizedDirection);
                    axisGroup.add(head);

                    return axisGroup;
                }

                axisRoot.add(createArrowAxis(new THREE.Vector3(1, 0, 0), 0xff5a5a));
                axisRoot.add(createArrowAxis(new THREE.Vector3(0, 1, 0), 0x4bd26b));
                axisRoot.add(createArrowAxis(new THREE.Vector3(0, 0, 1), 0x47a7ff));

                const axisLabels = [
                    { text: 'X', color: '#ff6b6b', position: new THREE.Vector3(0.85, 0, 0) },
                    { text: 'Y', color: '#51cf66', position: new THREE.Vector3(0, 0.85, 0) },
                    { text: 'Z', color: '#4dabf7', position: new THREE.Vector3(0, 0, 0.85) },
                ];

                function createAxisLabelSprite(text, color) {
                    const labelCanvas = document.createElement('canvas');
                    labelCanvas.width = 64;
                    labelCanvas.height = 64;
                    const context = labelCanvas.getContext('2d');
                    context.clearRect(0, 0, 64, 64);
                    context.beginPath();
                    context.arc(32, 32, 22, 0, Math.PI * 2);
                    context.fillStyle = 'rgba(0, 0, 0, 0.55)';
                    context.fill();
                    context.font = 'bold 50px Arial';
                    context.textAlign = 'center';
                    context.textBaseline = 'middle';
                    context.fillStyle = color;
                    context.fillText(text, 32, 34);

                    const texture = new THREE.CanvasTexture(labelCanvas);
                    const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
                    const sprite = new THREE.Sprite(material);
                    sprite.scale.set(0.22, 0.22, 0.22);
                    return sprite;
                }

                axisLabels.forEach((label) => {
                    const sprite = createAxisLabelSprite(label.text, label.color);
                    sprite.position.copy(label.position);
                    axisRoot.add(sprite);
                });

                function resizeRenderer() {
                    const width = canvas.clientWidth || canvas.parentElement.clientWidth || 800;
                    const height = canvas.clientHeight || 560;
                    renderer.setSize(width, height, false);
                    camera.aspect = width / height;
                    camera.updateProjectionMatrix();
                    axisRenderer.setSize(84, 84, false);
                    axisCamera.aspect = 1;
                    axisCamera.updateProjectionMatrix();
                }

                function frameObject(object) {
                    const box = new THREE.Box3().setFromObject(object);
                    const size = box.getSize(new THREE.Vector3());
                    const center = box.getCenter(new THREE.Vector3());
                    const maxDim = Math.max(size.x, size.y, size.z) || 1;
                    const fitDistance = maxDim / (2 * Math.tan((Math.PI * camera.fov) / 360));

                    object.position.sub(center);
                    controls.target.set(0, 0, 0);
                    camera.near = Math.max(maxDim / 100, 0.01);
                    camera.far = Math.max(maxDim * 100, 1000);
                    camera.position.set(fitDistance * 1.2, fitDistance * 0.8, fitDistance * 1.2);
                    camera.updateProjectionMatrix();
                    controls.update();
                }

                function onLoaded(object) {
                    scene.add(object);
                    frameObject(object);
                    setStatus('Drag to rotate - Scroll to zoom');
                }

                function loadModel() {
                    if (extension === 'glb' || extension === 'gltf') {
                        new THREE.GLTFLoader().load(modelUrl, (gltf) => onLoaded(gltf.scene), onProgress, onError);
                    } else if (extension === 'obj') {
                        new THREE.OBJLoader().load(modelUrl, (obj) => onLoaded(obj), onProgress, onError);
                    } else if (extension === 'fbx') {
                        new THREE.FBXLoader().load(modelUrl, (fbx) => onLoaded(fbx), onProgress, onError);
                    } else {
                        onError(new Error('Unsupported 3D preview format'));
                    }
                }

                resizeRenderer();
                window.addEventListener('resize', resizeRenderer);

                let animationFrameId = 0;
                function animate() {
                    animationFrameId = requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                    axisRoot.quaternion.copy(camera.quaternion).invert();
                    axisRenderer.render(axisScene, axisCamera);
                }

                animate();
                loadModel();

                new THREE.RGBELoader().load(
                    hdrUrl,
                    (hdrTexture) => {
                        const envMap = pmremGenerator.fromEquirectangular(hdrTexture).texture;
                        scene.environment = envMap;

                        const hdrBackground = new THREE.Mesh(
                            new THREE.SphereGeometry(2000, 2000, 2000),
                            new THREE.MeshBasicMaterial({
                                map: hdrTexture,
                                side: THREE.BackSide,
                                depthWrite: false
                            })
                        );
                        hdrBackground.rotation.x = Math.PI / 2;
                        scene.add(hdrBackground);

                        pmremGenerator.dispose();
                    },
                    undefined,
                    (error) => {
                        console.warn('HDR environment could not be loaded; continuing without it.', error);
                        scene.background = new THREE.Color(0x151515);
                        pmremGenerator.dispose();
                    }
                );
            })
            .catch((error) => {
                onError(error);
            });
    })();
</script>
