<?php

/**
 * @var $this CLSGridView
 * @var $massiveActionTemplate string
 * 
 * path: plugins/AutoArchive/widget/views/template.php
 */
?>

<div id="bottom-scroller" class="content-right scrolling-wrapper">
    {items}
</div>
    <div class="grid-view-ls-footer">
            <div class="massive-action-container" id="massive-action-container">
                <?= $massiveActionTemplate ?>
            </div>
            <div class="pagination-container">{pager}</div>
            <div class="summary-container">{summary}</div>
    </div>
<?php
if (!empty($this->lsAdditionalColumns)) {
    App()->getController()->widget('ext.admin.grid.ColumnFilterWidget.ColumnFilterWidget', [
        'modalId'           => 'survey-column-filter-modal',
        'filterableColumns' => $this->lsAdditionalColumns,
        'filteredColumns'   => $this->lsAdditionalColumnsSelected,
        'columnsData'       => $this->columns,
    ]);
}
?>
