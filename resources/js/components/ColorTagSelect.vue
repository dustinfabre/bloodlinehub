<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Settings, Plus, Pencil, Trash2, Check, X } from 'lucide-vue-next';
import axios from 'axios';

interface ColorTag {
    id: number;
    name: string;
    color: string;
}

const props = defineProps<{
    modelValue: number | null;
    colorTags: ColorTag[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

// Local state
const showManageModal = ref(false);
const isCreating = ref(false);
const newTagName = ref('');
const newTagColor = ref('#3B82F6');
const editingTag = ref<ColorTag | null>(null);
const editName = ref('');
const editColor = ref('');
const error = ref('');
const isSubmitting = ref(false);

// Selected value as string for Select component
const selectedValue = computed({
    get: () => props.modelValue?.toString() ?? 'none',
    set: (value: string) => {
        emit('update:modelValue', value === 'none' ? null : parseInt(value));
    }
});

// Get the selected color tag
const selectedTag = computed(() => 
    props.colorTags.find(t => t.id === props.modelValue) ?? null
);

// Open manage modal
const openManageModal = () => {
    showManageModal.value = true;
    error.value = '';
    isCreating.value = false;
    editingTag.value = null;
};

// Start creating a new tag
const startCreating = () => {
    isCreating.value = true;
    newTagName.value = '';
    newTagColor.value = '#3B82F6';
    error.value = '';
};

// Cancel creating
const cancelCreating = () => {
    isCreating.value = false;
    newTagName.value = '';
    newTagColor.value = '#3B82F6';
    error.value = '';
};

// Save new tag
const saveNewTag = async () => {
    if (!newTagName.value.trim() || isSubmitting.value) return;
    
    isSubmitting.value = true;
    error.value = '';
    
    try {
        const response = await axios.post('/color-tags', {
            name: newTagName.value.trim(),
            color: newTagColor.value,
        });
        
        // Add to local list
        props.colorTags.push(response.data);
        cancelCreating();
    } catch (e: any) {
        error.value = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Failed to create color tag';
    } finally {
        isSubmitting.value = false;
    }
};

// Start editing a tag
const startEditing = (tag: ColorTag) => {
    editingTag.value = tag;
    editName.value = tag.name;
    editColor.value = tag.color;
    error.value = '';
};

// Cancel editing
const cancelEditing = () => {
    editingTag.value = null;
    editName.value = '';
    editColor.value = '';
    error.value = '';
};

// Save edited tag
const saveEdit = async () => {
    if (!editingTag.value || !editName.value.trim() || isSubmitting.value) return;
    
    isSubmitting.value = true;
    error.value = '';
    
    try {
        const response = await axios.patch(`/color-tags/${editingTag.value.id}`, {
            name: editName.value.trim(),
            color: editColor.value,
        });
        
        // Update in local list
        const index = props.colorTags.findIndex(t => t.id === editingTag.value!.id);
        if (index >= 0) {
            props.colorTags[index] = response.data;
        }
        cancelEditing();
    } catch (e: any) {
        error.value = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Failed to update color tag';
    } finally {
        isSubmitting.value = false;
    }
};

// Delete a tag
const deleteTag = async (tag: ColorTag) => {
    if (!confirm(`Delete "${tag.name}"? Pigeons using this tag will have their highlight removed.`)) return;
    
    try {
        await axios.delete(`/color-tags/${tag.id}`);
        
        // Remove from local list
        const index = props.colorTags.findIndex(t => t.id === tag.id);
        if (index >= 0) {
            props.colorTags.splice(index, 1);
        }
        
        // If this was selected, clear selection
        if (props.modelValue === tag.id) {
            emit('update:modelValue', null);
        }
        
        error.value = '';
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Failed to delete color tag';
    }
};

// Preset colors for quick selection
const presetColors = [
    '#EF4444', // Red
    '#F97316', // Orange
    '#F59E0B', // Amber
    '#EAB308', // Yellow
    '#84CC16', // Lime
    '#22C55E', // Green
    '#14B8A6', // Teal
    '#06B6D4', // Cyan
    '#3B82F6', // Blue
    '#6366F1', // Indigo
    '#8B5CF6', // Violet
    '#A855F7', // Purple
    '#D946EF', // Fuchsia
    '#EC4899', // Pink
    '#64748B', // Slate
];
</script>

<template>
    <div class="space-y-2">
        <!-- Color Tag Dropdown -->
        <div class="flex gap-2">
            <Select v-model="selectedValue" class="flex-1">
                <SelectTrigger>
                    <SelectValue placeholder="No highlight color">
                        <span v-if="selectedTag" class="flex items-center gap-2">
                            <span 
                                class="w-3 h-3 rounded-full border border-border flex-shrink-0" 
                                :style="{ backgroundColor: selectedTag.color }"
                            />
                            {{ selectedTag.name }}
                        </span>
                        <span v-else class="text-muted-foreground">No highlight color</span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">
                        <span class="text-muted-foreground">No highlight color</span>
                    </SelectItem>
                    <SelectItem v-for="tag in colorTags" :key="tag.id" :value="tag.id.toString()">
                        <span class="flex items-center gap-2">
                            <span 
                                class="w-3 h-3 rounded-full border border-border flex-shrink-0" 
                                :style="{ backgroundColor: tag.color }"
                            />
                            {{ tag.name }}
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button type="button" variant="outline" size="icon" @click="openManageModal" title="Manage colors">
                <Settings class="h-4 w-4" />
            </Button>
        </div>

        <!-- Manage Colors Modal -->
        <Dialog v-model:open="showManageModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Manage Highlight Colors</DialogTitle>
                    <DialogDescription>
                        Create color presets to highlight special pigeons. Colors will be displayed on cards and pedigree charts.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-80 overflow-auto space-y-2">
                    <!-- Error message -->
                    <div v-if="error" class="p-2 bg-destructive/10 text-destructive text-sm rounded">
                        {{ error }}
                    </div>

                    <!-- Existing tags -->
                    <div v-if="colorTags.length === 0 && !isCreating" class="py-4 text-center text-muted-foreground">
                        No color tags yet. Create one to highlight special pigeons.
                    </div>

                    <div v-for="tag in colorTags" :key="tag.id" class="flex items-center gap-2 p-2 rounded hover:bg-accent/50">
                        <template v-if="editingTag?.id === tag.id">
                            <!-- Editing mode -->
                            <input
                                type="color"
                                v-model="editColor"
                                class="w-8 h-8 rounded cursor-pointer border-0 p-0"
                            />
                            <Input
                                v-model="editName"
                                class="flex-1"
                                placeholder="Tag name"
                                @keydown.enter="saveEdit"
                                @keydown.escape="cancelEditing"
                                autofocus
                            />
                            <Button size="sm" variant="ghost" @click="saveEdit" :disabled="isSubmitting">
                                <Check class="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="ghost" @click="cancelEditing">
                                <X class="h-4 w-4" />
                            </Button>
                        </template>
                        <template v-else>
                            <!-- Display mode -->
                            <span 
                                class="w-6 h-6 rounded border border-border flex-shrink-0" 
                                :style="{ backgroundColor: tag.color }"
                            />
                            <span class="flex-1">{{ tag.name }}</span>
                            <Button size="sm" variant="ghost" @click="startEditing(tag)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="ghost" @click="deleteTag(tag)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </template>
                    </div>

                    <!-- New tag form -->
                    <div v-if="isCreating" class="border rounded-md p-3 space-y-3">
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                v-model="newTagColor"
                                class="w-8 h-8 rounded cursor-pointer border-0 p-0"
                            />
                            <Input
                                v-model="newTagName"
                                class="flex-1"
                                placeholder="e.g., Top Breeder, Winner, Foundation"
                                @keydown.enter="saveNewTag"
                                @keydown.escape="cancelCreating"
                                autofocus
                            />
                        </div>
                        
                        <!-- Preset colors -->
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="color in presetColors"
                                :key="color"
                                type="button"
                                @click="newTagColor = color"
                                class="w-6 h-6 rounded border-2 transition-all"
                                :class="newTagColor === color ? 'border-primary scale-110' : 'border-transparent hover:border-muted-foreground'"
                                :style="{ backgroundColor: color }"
                            />
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button size="sm" variant="outline" @click="cancelCreating">Cancel</Button>
                            <Button size="sm" @click="saveNewTag" :disabled="!newTagName.trim() || isSubmitting">
                                Create
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex-row justify-between sm:justify-between">
                    <Button v-if="!isCreating" variant="outline" @click="startCreating">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Color
                    </Button>
                    <div v-else />
                    <Button variant="outline" @click="showManageModal = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
