<?php

/**
 * -------------------------------------------------------------------------
 * DataInjection plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of DataInjection.
 *
 * DataInjection is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * DataInjection is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Manages the invoice document shared by all assets created in one import.
 */
final class PluginDatainjectionInvoiceDocument
{
    /**
     * Check whether the model imports assets that can have financial data.
     *
     * @param string $itemtype GLPI item type
     *
     * @return bool
     */
    public static function isRequiredForItemtype($itemtype)
    {
        return class_exists($itemtype)
            && is_a($itemtype, CommonDBTM::class, true)
            && Infocom::canApplyOn($itemtype);
    }

    /**
     * Create a native GLPI Document from an uploaded PDF.
     *
     * @param array $file       Entry from $_FILES
     * @param int   $entitiesId Entity receiving the document
     *
     * @return int Document ID
     *
     * @throws RuntimeException
     */
    public static function createFromUpload(array $file, $entitiesId)
    {
        if (!Session::haveRight(Document::$rightname, CREATE)) {
            throw new RuntimeException(__('You do not have permission to create documents.'));
        }

        self::validatePdf($file);

        $originalName = basename((string) $file['name']);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        if (!$safeName) {
            $safeName = 'invoice.pdf';
        }

        $prefix = bin2hex(random_bytes(12)) . '_';
        $temporaryName = $prefix . $safeName;
        $temporaryPath = GLPI_TMP_DIR . DIRECTORY_SEPARATOR . $temporaryName;

        if (!move_uploaded_file($file['tmp_name'], $temporaryPath)) {
            throw new RuntimeException(
                __('The invoice PDF could not be moved to the GLPI temporary directory.', 'datainjection')
            );
        }

        try {
            $document = new Document();
            $documentId = $document->add([
                'name'                    => sprintf(__('Invoice - %s', 'datainjection'), $safeName),
                'entities_id'             => (int) $entitiesId,
                'is_recursive'            => 0,
                '_filename'               => [$temporaryName],
                '_prefix_filename'        => [$prefix],
                '_only_if_upload_succeed' => true,
            ]);

            if (!$documentId) {
                throw new RuntimeException(
                    __('GLPI could not create the invoice document.', 'datainjection')
                );
            }

            return (int) $documentId;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * Associate the invoice with an item through the native GLPI relation.
     *
     * @param int    $documentId Document ID
     * @param string $itemtype   Linked item type
     * @param int    $itemsId    Linked item ID
     *
     * @return int Relation ID
     *
     * @throws RuntimeException
     */
    public static function associate($documentId, $itemtype, $itemsId)
    {
        $relation = new Document_Item();
        $relationId = $relation->add([
            'documents_id' => (int) $documentId,
            'itemtype'     => $itemtype,
            'items_id'     => (int) $itemsId,
            'is_private'   => 0,
        ]);

        if (!$relationId) {
            throw new RuntimeException(
                sprintf(
                    __('Could not associate document #%1$d with %2$s #%3$d.', 'datainjection'),
                    $documentId,
                    $itemtype,
                    $itemsId
                )
            );
        }

        return (int) $relationId;
    }

    /**
     * Associate the invoice with an asset and its Infocom, when present.
     *
     * @param int    $documentId Invoice document ID
     * @param string $itemtype   Asset type
     * @param int    $itemsId    Asset ID
     *
     * @return int|null Infocom ID
     */
    public static function associateAsset($documentId, $itemtype, $itemsId)
    {
        self::associate($documentId, $itemtype, $itemsId);

        $infocom = new Infocom();
        if (!$infocom->getFromDBforDevice($itemtype, $itemsId)) {
            return null;
        }

        self::associate($documentId, Infocom::class, (int) $infocom->getID());

        return (int) $infocom->getID();
    }

    /**
     * Delete a document only when it has no item associations.
     *
     * @param int $documentId Document ID
     *
     * @return void
     */
    public static function purgeIfOrphan($documentId)
    {
        if (
            $documentId <= 0
            || countElementsInTable(
                Document_Item::getTable(),
                ['documents_id' => (int) $documentId]
            ) > 0
        ) {
            return;
        }

        $document = new Document();
        if ($document->getFromDB((int) $documentId)) {
            $document->delete(['id' => (int) $documentId], true);
        }
    }

    /**
     * Validate that the upload is a real PDF accepted by GLPI.
     *
     * @param array $file Entry from $_FILES
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private static function validatePdf(array $file)
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                sprintf(__('Invoice PDF upload failed with error code %d.', 'datainjection'), $error)
            );
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');

        if (!$temporaryPath || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException(__('The uploaded invoice PDF is invalid.', 'datainjection'));
        }

        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException(__('The invoice must have a PDF extension.', 'datainjection'));
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $header = file_get_contents($temporaryPath, false, null, 0, 5);

        if ($mime !== 'application/pdf' || $header !== '%PDF-') {
            throw new RuntimeException(__('The invoice must be a valid PDF file.', 'datainjection'));
        }

        if (!Document::isValidDoc($originalName)) {
            throw new RuntimeException(
                __('PDF uploads are not enabled in the GLPI document types configuration.', 'datainjection')
            );
        }
    }
}
