import { ref } from 'vue'

const axios = window.axios

// Module-level singletons — shared across all AdvancedMediaLibrary instances
const items      = ref([])
const breadcrumb = ref([])
const loading    = ref(false)
const folder     = ref(null)

async function fetchItems(folderPath = null) {
    loading.value = true
    folder.value  = folderPath ?? null
    try {
        const params = folderPath ? { folder: folderPath } : {}
        const { data } = await axios.get(route('admin.advanced-media.index'), { params })
        items.value      = data.items      ?? []
        breadcrumb.value = data.breadcrumb ?? []
    } finally {
        loading.value = false
    }
}

async function createFolder(name, parent = null) {
    const { data } = await axios.post(route('admin.advanced-media.folder'), { name, parent })
    items.value.push(data.item)
    return data.item
}

async function uploadFile(file, folderPath = null) {
    const form = new FormData()
    form.append('file', file)
    if (folderPath) form.append('folder', folderPath)
    const { data } = await axios.post(route('admin.advanced-media.upload'), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    })
    items.value.push(data.item)
    return data.item
}

async function renameItem(path, name, isFolder) {
    const { data } = await axios.patch(route('admin.advanced-media.rename'), {
        path, name, is_folder: isFolder,
    })
    const idx = items.value.findIndex(i => i.path === path)
    if (idx !== -1) Object.assign(items.value[idx], data.item)
    return data.item
}

async function deleteItem(path, isFolder) {
    await axios.delete(route('admin.advanced-media.destroy'), { data: { path, is_folder: isFolder } })
    items.value = items.value.filter(i => i.path !== path)
}

async function moveItem(path, targetFolder, isFolder) {
    const { data } = await axios.patch(route('admin.advanced-media.move'), {
        path, target_folder: targetFolder, is_folder: isFolder,
    })
    items.value = items.value.filter(i => i.path !== path)
    return data.item
}

export function useMediaLibrary() {
    return {
        items, breadcrumb, loading, folder,
        fetchItems, createFolder, uploadFile, renameItem, deleteItem, moveItem,
    }
}
