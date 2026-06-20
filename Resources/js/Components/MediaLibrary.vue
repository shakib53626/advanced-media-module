<script setup>
import { ref, computed, watch, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import { LayoutGrid, LayoutList } from 'lucide-vue-next'
import { useMediaLibrary } from '@modules/AdvancedMedia/Resources/js/Composables/useMediaLibrary'
import Sheet from '@/Components/UiComponent/Sheet.vue'
import Button from '@/Components/UiComponent/Button.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    multiple:   { type: Boolean, default: false },
    accept:     { type: String,  default: '' },
    title:      { type: String,  default: '' },
})

const emit = defineEmits(['update:modelValue', 'select'])

const { items, breadcrumb: apiBreadcrumb, loading, fetchItems, createFolder, uploadFile, deleteItem: apiDelete } = useMediaLibrary()

// safe $t
const _instance = getCurrentInstance()
function $t(key) {
    const fn = _instance?.appContext?.config?.globalProperties?.$t
    return fn ? fn(key) : key.split('.').pop()
}

// ── Mobile detection ──────────────────────────────────────────────────────────
const isMobile = ref(false)
let mq = null
const updateIsMobile = () => { isMobile.value = mq ? mq.matches : window.innerWidth < 640 }
onMounted(() => {
    mq = window.matchMedia('(max-width: 639.98px)')
    updateIsMobile()
    mq.addEventListener?.('change', updateIsMobile)
})
onUnmounted(() => { mq?.removeEventListener?.('change', updateIsMobile) })

// ── State ─────────────────────────────────────────────────────────────────────
const currentFolder   = ref(null)
const searchQuery     = ref('')
const viewMode        = ref('grid')
const selectedIds     = ref(new Set())
const selectedItem    = ref(null)
const confirmDeleteId = ref(null)
const showNewFolder   = ref(false)
const newFolderName   = ref('')
const dragOver        = ref(false)

// ── Reset on open ─────────────────────────────────────────────────────────────
watch(() => props.modelValue, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
    if (val) {
        currentFolder.value   = null
        searchQuery.value     = ''
        selectedIds.value     = new Set()
        selectedItem.value    = null
        confirmDeleteId.value = null
        showNewFolder.value   = false
        newFolderName.value   = ''
        dragOver.value        = false
        fetchItems(null)
    }
})
onUnmounted(() => { document.body.style.overflow = '' })

const onKeydown = (e) => { if (e.key === 'Escape' && props.modelValue) close() }
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))

// ── Helpers ───────────────────────────────────────────────────────────────────
const acceptedTypes = computed(() => {
    if (!props.accept) return null
    return props.accept.split(',').map(s => s.trim()).filter(Boolean)
})

function isAccepted(item) {
    if (item.type === 'folder') return true
    if (!acceptedTypes.value) return true
    return acceptedTypes.value.includes(item.type)
}

function formatSize(bytes) {
    if (!bytes) return '—'
    if (bytes < 1024)        return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function fileColor(type) {
    return ({ folder: 'text-amber-500', image: 'text-violet-500', pdf: 'text-red-500', doc: 'text-blue-500', video: 'text-pink-500', zip: 'text-orange-500' })[type] ?? 'text-semidark'
}

function fileBg(type) {
    return ({ folder: 'bg-amber-50 dark:bg-amber-500/10', image: 'bg-violet-50 dark:bg-violet-500/10', pdf: 'bg-red-50 dark:bg-red-500/10', doc: 'bg-blue-50 dark:bg-blue-500/10', video: 'bg-pink-50 dark:bg-pink-500/10', zip: 'bg-orange-50 dark:bg-orange-500/10' })[type] ?? 'bg-page'
}

function isImageType(type) { return type === 'image' }

// ── Sidebar folders ───────────────────────────────────────────────────────────
const sidebarFolders = computed(() => items.value.filter(i => i.type === 'folder'))

// ── Breadcrumb ────────────────────────────────────────────────────────────────
const localBreadcrumb = computed(() => {
    const crumbs = [{ id: null, name: $t('navigation.media_picker.all_files'), path: null }]
    if (apiBreadcrumb.value?.length) {
        apiBreadcrumb.value.forEach(c => crumbs.push(c))
    }
    return crumbs
})

// ── Visible items ─────────────────────────────────────────────────────────────
const visibleItems = computed(() => {
    let list = items.value.filter(i => isAccepted(i))
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter(i => i.name.toLowerCase().includes(q))
    }
    return [...list].sort((a, b) => {
        if (a.type === 'folder' && b.type !== 'folder') return -1
        if (a.type !== 'folder' && b.type === 'folder') return  1
        return a.name.localeCompare(b.name)
    })
})

// ── Upload (drag & drop / file input) ────────────────────────────────────────
function handleDrop(e) {
    dragOver.value = false
    addFiles(Array.from(e.dataTransfer?.files ?? []))
}
function handleFileInput(e) {
    addFiles(Array.from(e.target.files ?? []))
    e.target.value = ''
}
async function addFiles(files) {
    const folderPath = currentFolder.value
        ? items.value.find(i => i.id === currentFolder.value)?.path ?? null
        : null
    for (const f of files) {
        try { await uploadFile(f, folderPath) } catch (e) { console.error('Upload failed:', f.name, e) }
    }
}

// ── New folder ────────────────────────────────────────────────────────────────
async function submitNewFolder() {
    const name = newFolderName.value.trim()
    if (!name) return
    const parentPath = currentFolder.value
        ? items.value.find(i => i.id === currentFolder.value)?.path ?? null
        : null
    try {
        await createFolder(name, parentPath)
        newFolderName.value = ''
        showNewFolder.value = false
    } catch (e) { console.error('Create folder failed:', e) }
}

// ── Selection ─────────────────────────────────────────────────────────────────
function isSelected(item) {
    if (props.multiple) return selectedIds.value.has(item.id)
    return selectedItem.value?.id === item.id
}
function clickItem(item) {
    if (item.type === 'folder') {
        currentFolder.value = item.id
        fetchItems(item.path)
        return
    }
    if (props.multiple) {
        const s = new Set(selectedIds.value)
        s.has(item.id) ? s.delete(item.id) : s.add(item.id)
        selectedIds.value = s
    } else {
        selectedItem.value = isSelected(item) ? null : item
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────
function askDelete(item, e) { e.stopPropagation(); confirmDeleteId.value = item.id }
async function doDelete() {
    const id   = confirmDeleteId.value
    const item = items.value.find(i => i.id === id)
    if (!item) { confirmDeleteId.value = null; return }
    try {
        await apiDelete(item.path, item.type === 'folder')
        if (!props.multiple && selectedItem.value?.id === id) selectedItem.value = null
        if (props.multiple) { const s = new Set(selectedIds.value); s.delete(id); selectedIds.value = s }
    } catch (e) { console.error('Delete failed:', e) }
    confirmDeleteId.value = null
}

// ── Bottom bar ────────────────────────────────────────────────────────────────
const bottomSelected = computed(() => {
    if (props.multiple) {
        const arr = items.value.filter(i => selectedIds.value.has(i.id))
        return arr.length ? arr : null
    }
    return selectedItem.value ? [selectedItem.value] : null
})
const hasSelection = computed(() => props.multiple ? selectedIds.value.size > 0 : selectedItem.value !== null)
const selectBtnLabel = computed(() => {
    if (props.multiple && selectedIds.value.size > 1) return `Select (${selectedIds.value.size})`
    return 'Select'
})

// ── Actions ───────────────────────────────────────────────────────────────────
function close() { emit('update:modelValue', false) }
function confirmSelect() {
    if (!hasSelection.value) return
    if (props.multiple) {
        emit('select', items.value.filter(i => selectedIds.value.has(i.id)))
    } else {
        emit('select', selectedItem.value)
    }
    close()
}
</script>

<template>
    <!-- ── DESKTOP: Full modal ───────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="aml-modal">
            <div
                v-if="modelValue && !isMobile"
                class="fixed inset-0 z-200 flex items-center justify-center p-4"
            >
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close" />

                <div class="relative z-10 w-full max-w-5xl bg-card border border-stroke1 rounded-2xl shadow-2xl flex flex-col overflow-hidden" style="height: min(88vh, 680px)">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-stroke1 shrink-0">
                        <h3 class="text-base font-semibold text-dark">{{ title || $t('navigation.media_picker.title') }}</h3>
                        <button class="w-7 h-7 flex items-center justify-center rounded-md text-semidark hover:text-dark hover:bg-stroke1 transition-colors" @click="close">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <!-- Body: sidebar + content -->
                    <div class="flex flex-1 min-h-0">

                        <!-- Left sidebar -->
                        <aside class="w-48 shrink-0 border-e border-stroke1 flex flex-col bg-page overflow-y-auto">
                            <!-- All Files -->
                            <button
                                class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition-colors w-full text-start"
                                :class="currentFolder === null ? 'bg-primary/10 text-primary font-medium' : 'text-semidark hover:bg-stroke1 hover:text-dark'"
                                @click="currentFolder = null; fetchItems(null)"
                            >
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <span class="truncate">{{ $t('navigation.media_picker.all_files') }}</span>
                            </button>

                            <div class="h-px bg-stroke1 mx-3 my-1" />

                            <!-- Folders -->
                            <button
                                v-for="folder in sidebarFolders"
                                :key="folder.id"
                                class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition-colors w-full text-start"
                                :class="currentFolder === folder.id ? 'bg-primary/10 text-primary font-medium' : 'text-semidark hover:bg-stroke1 hover:text-dark'"
                                @click="currentFolder = folder.id; fetchItems(folder.path)"
                            >
                                <svg class="w-4 h-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span class="truncate">{{ folder.name }}</span>
                            </button>

                            <!-- New folder row -->
                            <div class="mt-auto p-3 border-t border-stroke1">
                                <div v-if="showNewFolder" class="flex gap-1">
                                    <input v-model="newFolderName" type="text" placeholder="Folder name" class="flex-1 px-2 py-1 text-xs rounded border border-stroke1 bg-card focus:outline-none focus:border-primary" @keyup.enter="submitNewFolder" @keyup.escape="showNewFolder = false" />
                                    <button @click="submitNewFolder" class="px-2 py-1 text-xs rounded bg-primary text-white">OK</button>
                                </div>
                                <button v-else @click="showNewFolder = true" class="flex items-center gap-1.5 text-xs text-semidark hover:text-primary transition-colors w-full">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                                    New folder
                                </button>
                            </div>
                        </aside>

                        <!-- Right content -->
                        <div class="flex-1 flex flex-col min-w-0 min-h-0">

                            <!-- Toolbar -->
                            <div class="flex items-center gap-2 px-4 py-2.5 border-b border-stroke1 shrink-0">
                                <!-- Breadcrumb -->
                                <nav class="flex items-center gap-1 flex-1 min-w-0 text-xs">
                                    <template v-for="(crumb, idx) in localBreadcrumb" :key="crumb.id ?? 'root'">
                                        <button
                                            @click="currentFolder = crumb.id; fetchItems(crumb.path ?? null)"
                                            class="shrink-0 transition-colors"
                                            :class="idx === localBreadcrumb.length - 1 ? 'text-dark font-medium pointer-events-none' : 'text-semidark hover:text-primary'"
                                        >{{ crumb.name }}</button>
                                        <svg v-if="idx < localBreadcrumb.length - 1" class="w-3 h-3 text-stroke3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                                    </template>
                                </nav>

                                <!-- Search -->
                                <div class="relative w-44 shrink-0">
                                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-semidark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                    <input v-model="searchQuery" type="text" :placeholder="$t('navigation.media_picker.search_placeholder')" class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-stroke1 bg-page focus:outline-none focus:border-primary" />
                                </div>

                                <!-- Upload -->
                                <label class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-primary text-white cursor-pointer shrink-0 hover:bg-primary/90 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Upload
                                    <input type="file" multiple class="hidden" @change="handleFileInput" />
                                </label>

                                <!-- View toggle -->
                                <div class="flex items-center gap-0.5 border border-stroke1 rounded-xl p-0.5 shrink-0">
                                    <Button size="icon" variant="ghost" @click="viewMode = 'grid'" class="w-7 h-7 rounded-[9px]" :class="viewMode === 'grid' ? 'bg-primary text-white hover:bg-primary' : 'text-semidark'">
                                        <LayoutGrid class="w-3.5 h-3.5" />
                                    </Button>
                                    <Button size="icon" variant="ghost" @click="viewMode = 'list'" class="w-7 h-7 rounded-[9px]" :class="viewMode === 'list' ? 'bg-primary text-white hover:bg-primary' : 'text-semidark'">
                                        <LayoutList class="w-3.5 h-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <!-- File area -->
                            <div
                                class="flex-1 overflow-y-auto p-3 min-h-0"
                                :class="dragOver ? 'bg-primary/5' : ''"
                                @dragover.prevent="dragOver = true"
                                @dragleave.self="dragOver = false"
                                @drop.prevent="handleDrop"
                            >
                                <!-- Drag overlay -->
                                <div v-if="dragOver" class="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
                                    <div class="flex flex-col items-center gap-2 text-primary">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span class="text-sm font-medium">Drop files here</span>
                                    </div>
                                </div>

                                <!-- Loading -->
                                <div v-if="loading" class="flex items-center justify-center h-full min-h-40">
                                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                                </div>

                                <!-- Empty -->
                                <div v-else-if="visibleItems.length === 0" class="flex flex-col items-center justify-center h-full min-h-40 text-center py-10">
                                    <div class="w-12 h-12 rounded-2xl bg-page flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-stroke3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                    </div>
                                    <p class="text-sm text-semidark">{{ $t('navigation.media_picker.empty') }}</p>
                                </div>

                                <!-- Grid view -->
                                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2.5">
                                    <div
                                        v-for="item in visibleItems"
                                        :key="item.id"
                                        class="group relative rounded-xl border-2 p-2.5 cursor-pointer transition-all duration-150 select-none bg-card"
                                        :class="isSelected(item) ? 'border-primary ring-1 ring-primary/30 bg-primary/5' : 'border-stroke1 hover:border-stroke2 hover:shadow-sm'"
                                        @click="clickItem(item)"
                                    >
                                        <!-- Selection check -->
                                        <div
                                            v-if="item.type !== 'folder'"
                                            class="absolute top-1.5 start-1.5 w-4 h-4 rounded border flex items-center justify-center transition-opacity z-10"
                                            :class="isSelected(item) ? 'bg-primary border-primary opacity-100' : 'border-stroke2 opacity-0 group-hover:opacity-100 bg-card'"
                                        >
                                            <svg v-if="isSelected(item)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                        </div>

                                        <!-- Delete button -->
                                        <button
                                            v-if="item.type !== 'folder'"
                                            class="absolute top-1.5 end-1.5 w-5 h-5 rounded-full bg-black/40 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500 z-10"
                                            @click.stop="askDelete(item, $event)"
                                        >
                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>

                                        <!-- Thumbnail or icon -->
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mx-auto mb-2 overflow-hidden" :class="item.type === 'image' && item.url ? 'bg-stroke1/30' : fileBg(item.type)">
                                            <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                                            <svg v-else-if="item.type === 'folder'" class="w-6 h-6" :class="fileColor(item.type)" fill="currentColor" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                            <svg v-else-if="item.type === 'image'" class="w-5 h-5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <svg v-else-if="item.type === 'pdf'" class="w-5 h-5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                            <svg v-else-if="item.type === 'video'" class="w-5 h-5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                                            <svg v-else class="w-5 h-5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        </div>

                                        <p class="text-[11px] font-medium text-dark truncate text-center leading-tight" :title="item.name">{{ item.name }}</p>
                                        <p class="text-[10px] text-semidark text-center mt-0.5">
                                            {{ item.type === 'folder' ? `${item.children ?? 0} items` : formatSize(item.size) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- List view -->
                                <div v-else class="bg-card border border-stroke1 rounded-xl overflow-hidden">
                                    <table class="w-full text-sm">
                                        <tbody class="divide-y divide-stroke1">
                                            <tr
                                                v-for="item in visibleItems"
                                                :key="item.id"
                                                class="cursor-pointer transition-colors group"
                                                :class="isSelected(item) ? 'bg-primary/5' : 'hover:bg-page'"
                                                @click="clickItem(item)"
                                            >
                                                <td class="px-3 py-2.5 w-8">
                                                    <div
                                                        v-if="item.type !== 'folder'"
                                                        class="w-4 h-4 rounded border flex items-center justify-center transition-colors"
                                                        :class="isSelected(item) ? 'bg-primary border-primary' : 'border-stroke2 group-hover:border-stroke3'"
                                                        @click.stop="clickItem(item)"
                                                    >
                                                        <svg v-if="isSelected(item)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </div>
                                                </td>
                                                <td class="px-2 py-2.5">
                                                    <div class="flex items-center gap-2.5">
                                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" :class="item.type === 'image' && item.url ? 'bg-stroke1/30' : fileBg(item.type)">
                                                            <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                                                            <svg v-else-if="item.type === 'folder'" class="w-4 h-4" :class="fileColor(item.type)" fill="currentColor" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                                            <svg v-else class="w-3.5 h-3.5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                        </div>
                                                        <span class="text-sm font-medium text-dark truncate">{{ item.name }}</span>
                                                        <span v-if="item.type === 'folder'" class="text-xs text-primary">→</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5 text-xs text-semidark w-24 text-end">
                                                    {{ item.type === 'folder' ? `${item.children ?? 0} items` : formatSize(item.size) }}
                                                </td>
                                                <td class="px-3 py-2.5 w-10">
                                                    <button
                                                        v-if="item.type !== 'folder'"
                                                        class="w-6 h-6 flex items-center justify-center rounded text-semidark hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
                                                        @click.stop="askDelete(item, $event)"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom bar -->
                    <div class="flex items-center gap-3 px-4 py-3 border-t border-stroke1 bg-page shrink-0">
                        <template v-if="bottomSelected && bottomSelected.length > 0">
                            <div v-if="!multiple" class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" :class="isImageType(bottomSelected[0].type) ? 'bg-stroke1' : fileBg(bottomSelected[0].type)">
                                    <img v-if="isImageType(bottomSelected[0].type) && bottomSelected[0].url" :src="bottomSelected[0].url" class="w-full h-full object-cover" alt="" />
                                    <svg v-else class="w-5 h-5" :class="fileColor(bottomSelected[0].type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-dark truncate">{{ bottomSelected[0].name }}</p>
                                    <p class="text-xs text-semidark">{{ formatSize(bottomSelected[0].size) }}</p>
                                </div>
                            </div>
                            <div v-else class="flex-1 min-w-0">
                                <p class="text-sm text-dark"><span class="font-medium">{{ selectedIds.size }}</span> <span class="text-semidark">{{ selectedIds.size === 1 ? 'file' : 'files' }} selected</span></p>
                            </div>
                        </template>
                        <div v-else class="flex-1 min-w-0">
                            <p class="text-sm text-semidark">{{ $t('navigation.media_picker.nothing_selected') }}</p>
                        </div>
                        <button @click="close" class="px-4 py-2 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-stroke1 transition-colors shrink-0">{{ $t('navigation.media_picker.cancel') }}</button>
                        <button @click="confirmSelect" :disabled="!hasSelection" class="px-4 py-2 text-sm rounded-lg bg-primary text-white transition-colors shrink-0" :class="hasSelection ? 'hover:bg-primary/90' : 'opacity-40 cursor-not-allowed'">{{ selectBtnLabel }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── MOBILE: Bottom Sheet ───────────────────────────────────────────── -->
    <Sheet v-if="isMobile" :model-value="modelValue && isMobile" @update:model-value="$emit('update:modelValue', $event)" side="bottom" size="full" :title="title || $t('navigation.media_picker.title')">
        <div class="flex flex-col h-full -mx-5 -my-4">
            <!-- Mobile toolbar -->
            <div class="flex flex-col gap-1.5 px-4 py-2.5 border-b border-stroke1 shrink-0">
                <!-- Breadcrumb -->
                <nav class="flex items-center gap-1 text-xs">
                    <template v-for="(crumb, idx) in localBreadcrumb" :key="crumb.id ?? 'root'">
                        <button @click="currentFolder = crumb.id; fetchItems(crumb.path ?? null)" class="shrink-0 transition-colors" :class="idx === localBreadcrumb.length - 1 ? 'text-dark font-medium pointer-events-none' : 'text-semidark hover:text-primary'">{{ crumb.name }}</button>
                        <svg v-if="idx < localBreadcrumb.length - 1" class="w-3 h-3 text-stroke3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </template>
                </nav>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-semidark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input v-model="searchQuery" type="text" :placeholder="$t('navigation.media_picker.search_placeholder')" class="w-full pl-8 pr-3 py-1.5 text-sm rounded-lg border border-stroke1 bg-page focus:outline-none focus:border-primary" />
                    </div>
                    <label class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-primary text-white cursor-pointer shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <input type="file" multiple class="hidden" @change="handleFileInput" />
                    </label>
                    <div class="flex items-center gap-0.5 border border-stroke1 rounded-xl p-0.5 shrink-0">
                        <Button size="icon" variant="ghost" @click="viewMode = 'grid'" class="w-7 h-7 rounded-lg" :class="viewMode === 'grid' ? 'bg-primary text-white hover:bg-primary' : 'text-semidark'"><LayoutGrid class="w-3.5 h-3.5" /></Button>
                        <Button size="icon" variant="ghost" @click="viewMode = 'list'" class="w-7 h-7 rounded-lg" :class="viewMode === 'list' ? 'bg-primary text-white hover:bg-primary' : 'text-semidark'"><LayoutList class="w-3.5 h-3.5" /></Button>
                    </div>
                </div>
            </div>

            <!-- Mobile file area -->
            <div class="flex-1 overflow-y-auto p-3 min-h-0">
                <div v-if="loading" class="flex items-center justify-center h-full min-h-40">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                </div>
                <div v-else-if="visibleItems.length === 0" class="flex flex-col items-center justify-center h-full min-h-40 text-center py-10">
                    <p class="text-sm text-semidark">{{ $t('navigation.media_picker.empty') }}</p>
                </div>
                <!-- Grid -->
                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-3 gap-2">
                    <div v-for="item in visibleItems" :key="item.id" class="group relative rounded-xl border-2 p-2 cursor-pointer transition-all duration-150 select-none bg-card" :class="isSelected(item) ? 'border-primary ring-1 ring-primary/30 bg-primary/5' : 'border-stroke1 hover:border-stroke2'" @click="clickItem(item)">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center mx-auto mb-1.5 overflow-hidden" :class="item.type === 'image' && item.url ? 'bg-stroke1/30' : fileBg(item.type)">
                            <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                            <svg v-else-if="item.type === 'folder'" class="w-5 h-5" :class="fileColor(item.type)" fill="currentColor" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            <svg v-else class="w-4 h-4" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <p class="text-[10px] font-medium text-dark truncate text-center leading-tight" :title="item.name">{{ item.name }}</p>
                    </div>
                </div>
                <!-- List -->
                <div v-else class="bg-card border border-stroke1 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-stroke1">
                            <tr v-for="item in visibleItems" :key="item.id" class="cursor-pointer transition-colors" :class="isSelected(item) ? 'bg-primary/5' : 'hover:bg-page'" @click="clickItem(item)">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" :class="fileBg(item.type)">
                                            <svg v-if="item.type === 'folder'" class="w-4 h-4" :class="fileColor(item.type)" fill="currentColor" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                            <svg v-else class="w-3.5 h-3.5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        </div>
                                        <span class="text-sm font-medium text-dark truncate">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-semidark text-end w-20">{{ item.type === 'folder' ? `${item.children ?? 0} items` : formatSize(item.size) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile bottom bar -->
            <div class="flex items-center gap-3 px-4 py-3 border-t border-stroke1 bg-page shrink-0">
                <template v-if="bottomSelected && bottomSelected.length > 0">
                    <div class="flex items-center gap-2.5 flex-1 min-w-0">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" :class="isImageType(bottomSelected[0].type) ? 'bg-stroke1' : fileBg(bottomSelected[0].type)">
                            <img v-if="isImageType(bottomSelected[0].type) && bottomSelected[0].url" :src="bottomSelected[0].url" class="w-full h-full object-cover" alt="" />
                            <svg v-else class="w-4 h-4" :class="fileColor(bottomSelected[0].type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-dark truncate">{{ multiple ? `${selectedIds.size} selected` : bottomSelected[0].name }}</p>
                            <p v-if="!multiple" class="text-xs text-semidark">{{ formatSize(bottomSelected[0].size) }}</p>
                        </div>
                    </div>
                </template>
                <div v-else class="flex-1">
                    <p class="text-sm text-semidark">{{ $t('navigation.media_picker.nothing_selected') }}</p>
                </div>
                <button @click="close" class="px-3 py-2 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-stroke1 transition-colors shrink-0">{{ $t('navigation.media_picker.cancel') }}</button>
                <button @click="confirmSelect" :disabled="!hasSelection" class="px-3 py-2 text-sm rounded-lg bg-primary text-white transition-colors shrink-0" :class="hasSelection ? 'hover:bg-primary/90' : 'opacity-40 cursor-not-allowed'">{{ selectBtnLabel }}</button>
            </div>
        </div>
    </Sheet>

    <!-- ── Delete confirm ────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="aml-modal">
            <div v-if="confirmDeleteId !== null" class="fixed inset-0 z-300 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmDeleteId = null" />
                <div class="relative z-10 bg-card border border-stroke1 rounded-2xl shadow-xl w-80 p-5">
                    <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-500/10 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-dark">Delete this file?</p>
                    <p class="text-xs text-semidark mt-1">This action cannot be undone.</p>
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="confirmDeleteId = null" class="px-3 py-1.5 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-page transition-colors">Cancel</button>
                        <button @click="doDelete" class="px-3 py-1.5 text-sm rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors">Delete</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.aml-modal-enter-active,
.aml-modal-leave-active { transition: all 0.22s ease; }
.aml-modal-enter-from,
.aml-modal-leave-to     { opacity: 0; }
.aml-modal-enter-from .relative,
.aml-modal-leave-to .relative { transform: scale(0.97) translateY(-6px); }
</style>
