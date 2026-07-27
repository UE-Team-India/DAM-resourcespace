<?php

$rsroot = dirname(dirname(__DIR__));
include "{$rsroot}/include/boot.php";
include "{$rsroot}/include/authenticate.php";
include_once "{$rsroot}/include/ajax_functions.php";
include_once "{$rsroot}/include/image_processing.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ajax_send_response(405, ajax_response_fail(ajax_build_message($lang['error-method-not_allowed'])));
}

$ref = getval('ref', 0, true);
$resource = get_resource_data($ref);
$supported_extensions = ['obj', 'fbx', 'gltf', 'glb'];

if (
    !is_array($resource)
    || !in_array(strtolower((string) $resource['file_extension']), $supported_extensions, true)
    || !can_upload_preview_image($ref)
    || !get_edit_access($ref, $resource['archive'], $resource)
) {
    ajax_send_response(403, ajax_response_fail(ajax_build_message($lang['error-permissiondenied'])));
}

if (!isset($_FILES['userfile']) || $_FILES['userfile']['error'] !== UPLOAD_ERR_OK) {
    ajax_send_response(400, ajax_response_fail(ajax_build_message($lang['error_upload_failed'])));
}

if (!upload_preview($ref)) {
    ajax_send_response(422, ajax_response_fail(ajax_build_message($lang['error_upload_failed'])));
}

ajax_send_response(
    200,
    ajax_response_ok([
        'thumbnail_url' => get_resource_path($ref, false, 'thm', false, 'jpg', true),
    ])
);
