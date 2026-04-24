'use client';

import { Spinner } from 'flowbite-react';
import { useMenuBuilder } from './components/useMenuBuilder';
import { MenuAlerts, MenuToolbar, MenuStatusBar } from './components/MenuBuilderToolbar';
import { MenuTreeDnd, FooterMenuTable } from './components/MenuTree';
import {
  AddCategoryModal,
  AddLinkModal,
  AddDropdownModal,
  DeleteMenuItemModal,
} from './components/MenuItemEditor';

// ============================================================================
// Orchestrator
// ============================================================================

interface MenuBuilderManagerProps {
  locale: string;
}

export default function MenuBuilderManager({ locale }: MenuBuilderManagerProps) {
  const mb = useMenuBuilder(locale);

  if (mb.loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <Spinner size="xl" />
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Alerts */}
      <MenuAlerts error={mb.error} success={mb.success} />

      {/* Tab Switcher + Actions */}
      <MenuToolbar
        activeTab={mb.activeTab}
        onTabChange={mb.setActiveTab}
        onAddDropdown={() => mb.setShowAddDropdownModal(true)}
        onAddCategory={() => mb.setShowAddCategoryModal(true)}
        onAddLink={() => mb.setShowAddLinkModal(true)}
        availableCategoriesCount={mb.availableCategories.length}
      />

      {/* Item count */}
      <MenuStatusBar
        totalItemCount={mb.totalItemCount}
        activeTab={mb.activeTab}
        saving={mb.saving}
      />

      {/* Empty state */}
      {mb.tree.length === 0 ? (
        <div className="text-center py-12 text-secondary dark:text-gray-400">
          No items in this menu yet. Add a category, external link, or dropdown to get started.
        </div>
      ) : mb.activeTab === 'main' ? (
        <MenuTreeDnd
          flatItems={mb.flatItems}
          sortableIds={mb.sortableIds}
          activeItem={mb.activeItem}
          expandedDropdowns={mb.expandedDropdowns}
          sensors={mb.sensors}
          saving={mb.saving}
          onDragStart={mb.handleDragStart}
          onDragEnd={mb.handleDragEnd}
          onToggleExpand={mb.toggleExpand}
          onDelete={mb.openDeleteModal}
        />
      ) : (
        <FooterMenuTable
          tree={mb.tree}
          saving={mb.saving}
          onMoveUp={mb.handleMoveUp}
          onMoveDown={mb.handleMoveDown}
          onDelete={mb.openDeleteModal}
        />
      )}

      {/* Modals */}
      <AddCategoryModal
        show={mb.showAddCategoryModal}
        onClose={() => mb.setShowAddCategoryModal(false)}
        saving={mb.saving}
        activeTab={mb.activeTab}
        availableCategories={mb.availableCategories}
        dropdownItems={mb.dropdownItems}
        selectedCategoryId={mb.selectedCategoryId}
        setSelectedCategoryId={mb.setSelectedCategoryId}
        addCategoryParent={mb.addCategoryParent}
        setAddCategoryParent={mb.setAddCategoryParent}
        onSubmit={mb.handleAddCategory}
      />

      <AddLinkModal
        show={mb.showAddLinkModal}
        onClose={() => mb.setShowAddLinkModal(false)}
        saving={mb.saving}
        activeTab={mb.activeTab}
        dropdownItems={mb.dropdownItems}
        linkLabel={mb.linkLabel}
        setLinkLabel={mb.setLinkLabel}
        linkUrl={mb.linkUrl}
        setLinkUrl={mb.setLinkUrl}
        linkNewTab={mb.linkNewTab}
        setLinkNewTab={mb.setLinkNewTab}
        addLinkParent={mb.addLinkParent}
        setAddLinkParent={mb.setAddLinkParent}
        onSubmit={mb.handleAddExternalLink}
      />

      <AddDropdownModal
        show={mb.showAddDropdownModal}
        onClose={() => mb.setShowAddDropdownModal(false)}
        saving={mb.saving}
        dropdownLabel={mb.dropdownLabel}
        setDropdownLabel={mb.setDropdownLabel}
        dropdownLabelEn={mb.dropdownLabelEn}
        setDropdownLabelEn={mb.setDropdownLabelEn}
        dropdownLabelRu={mb.dropdownLabelRu}
        setDropdownLabelRu={mb.setDropdownLabelRu}
        onSubmit={mb.handleAddDropdown}
      />

      <DeleteMenuItemModal
        show={mb.showDeleteModal}
        onClose={() => {
          mb.setShowDeleteModal(false);
        }}
        saving={mb.saving}
        deletingItem={mb.deletingItem}
        onConfirm={mb.handleDelete}
      />
    </div>
  );
}
