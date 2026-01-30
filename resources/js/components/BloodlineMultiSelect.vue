<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { X, Plus, Star, Search, Pencil, Trash2, Check } from 'lucide-vue-next';
import axios from 'axios';

interface BloodlineOption {
    id: number;
    name: string;
}

interface SelectedBloodline {
    id: number;
    name: string;
    is_primary: boolean;
}

const props = defineProps<{
    modelValue: SelectedBloodline[];
    bloodlines: BloodlineOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SelectedBloodline[]];
}>();

// Local state
const searchQuery = ref('');
const showDropdown = ref(false);
const isCreating = ref(false);
const showManageModal = ref(false);
const editingBloodline = ref<BloodlineOption | null>(null);
const editName = ref('');
const manageError = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const dropdownRef = ref<HTMLDivElement | null>(null);

// Computed list of available bloodlines (not already selected)
const availableBloodlines = computed(() => {
    const selectedIds = new Set(props.modelValue.map(b => b.id));
    return props.bloodlines.filter(b => !selectedIds.has(b.id));
});

// Filtered bloodlines based on search
const filteredBloodlines = computed(() => {
    if (!searchQuery.value) return availableBloodlines.value;
    const query = searchQuery.value.toLowerCase();
    return availableBloodlines.value.filter(b => 
        b.name.toLowerCase().includes(query)
    );
});

// Check if we can create a new bloodline with the current search query
const canCreateNew = computed(() => {
    if (!searchQuery.value.trim()) return false;
    const query = searchQuery.value.trim().toUpperCase();
    // Check if any existing bloodline matches exactly
    return !props.bloodlines.some(b => b.name.toUpperCase() === query);
});

// Select a bloodline
const selectBloodline = (bloodline: BloodlineOption) => {
    const newBloodline: SelectedBloodline = {
        id: bloodline.id,
        name: bloodline.name,
        is_primary: props.modelValue.length === 0, // First one is primary by default
    };
    emit('update:modelValue', [...props.modelValue, newBloodline]);
    searchQuery.value = '';
    showDropdown.value = false;
};

// Remove a bloodline
const removeBloodline = (id: number) => {
    const removed = props.modelValue.find(b => b.id === id);
    const remaining = props.modelValue.filter(b => b.id !== id);
    
    // If removed was primary and there are still others, make first one primary
    if (removed?.is_primary && remaining.length > 0) {
        remaining[0].is_primary = true;
    }
    
    emit('update:modelValue', remaining);
};

// Set a bloodline as primary
const setPrimary = (id: number) => {
    const updated = props.modelValue.map(b => ({
        ...b,
        is_primary: b.id === id,
    }));
    emit('update:modelValue', updated);
};

// Create a new bloodline
const createBloodline = async () => {
    if (!searchQuery.value.trim() || isCreating.value) return;
    
    isCreating.value = true;
    try {
        const response = await axios.post('/bloodlines/get-or-create', {
            name: searchQuery.value.trim(),
        });
        
        const newBloodline = response.data;
        
        // Add to selection
        const newSelected: SelectedBloodline = {
            id: newBloodline.id,
            name: newBloodline.name,
            is_primary: props.modelValue.length === 0,
        };
        emit('update:modelValue', [...props.modelValue, newSelected]);
        
        searchQuery.value = '';
        showDropdown.value = false;
    } catch (error) {
        console.error('Failed to create bloodline:', error);
    } finally {
        isCreating.value = false;
    }
};

// Handle input focus
const handleFocus = () => {
    showDropdown.value = true;
};

// Handle click outside
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as Node;
    if (
        dropdownRef.value && 
        !dropdownRef.value.contains(target) &&
        inputRef.value && 
        !inputRef.value.contains(target)
    ) {
        showDropdown.value = false;
    }
};

// Manage modal functions
const openManageModal = () => {
    showManageModal.value = true;
    showDropdown.value = false;
};

const startEditing = (bloodline: BloodlineOption) => {
    editingBloodline.value = bloodline;
    editName.value = bloodline.name;
    manageError.value = '';
};

const cancelEditing = () => {
    editingBloodline.value = null;
    editName.value = '';
    manageError.value = '';
};

const saveEdit = async () => {
    if (!editingBloodline.value || !editName.value.trim()) return;
    
    try {
        await axios.patch(`/bloodlines/${editingBloodline.value.id}`, {
            name: editName.value.trim(),
        });
        
        // Update local bloodlines list (will be refreshed on page reload)
        const index = props.bloodlines.findIndex(b => b.id === editingBloodline.value!.id);
        if (index >= 0) {
            props.bloodlines[index].name = editName.value.trim().toUpperCase();
        }
        
        // Update selected bloodlines if this one is selected
        const selectedIndex = props.modelValue.findIndex(b => b.id === editingBloodline.value!.id);
        if (selectedIndex >= 0) {
            const updated = [...props.modelValue];
            updated[selectedIndex].name = editName.value.trim().toUpperCase();
            emit('update:modelValue', updated);
        }
        
        cancelEditing();
    } catch (error: any) {
        manageError.value = error.response?.data?.message || 'Failed to update bloodline';
    }
};

const deleteBloodline = async (bloodline: BloodlineOption) => {
    if (!confirm(`Are you sure you want to delete "${bloodline.name}"?`)) return;
    
    try {
        await axios.delete(`/bloodlines/${bloodline.id}`);
        
        // Remove from local bloodlines list
        const index = props.bloodlines.findIndex(b => b.id === bloodline.id);
        if (index >= 0) {
            props.bloodlines.splice(index, 1);
        }
        
        // Remove from selected if present
        removeBloodline(bloodline.id);
        
        manageError.value = '';
    } catch (error: any) {
        manageError.value = error.response?.data?.message || 'Failed to delete bloodline';
    }
};

// Setup click outside listener
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="space-y-2">
        <!-- Selected bloodlines as chips -->
        <div v-if="modelValue.length > 0" class="flex flex-wrap gap-2">
            <Badge 
                v-for="bloodline in modelValue" 
                :key="bloodline.id"
                :class="[
                    'pl-2 pr-1 py-1 text-sm flex items-center gap-1',
                    bloodline.is_primary ? 'bg-primary text-primary-foreground' : 'bg-secondary text-secondary-foreground'
                ]"
            >
                <button
                    v-if="!bloodline.is_primary"
                    @click.stop="setPrimary(bloodline.id)"
                    class="hover:text-yellow-500 transition-colors"
                    title="Set as primary"
                >
                    <Star class="h-3 w-3" />
                </button>
                <Star v-else class="h-3 w-3 fill-current" title="Primary bloodline" />
                <span>{{ bloodline.name }}</span>
                <button
                    @click.stop="removeBloodline(bloodline.id)"
                    class="ml-1 hover:bg-black/10 rounded p-0.5 transition-colors"
                >
                    <X class="h-3 w-3" />
                </button>
            </Badge>
        </div>

        <!-- Search input -->
        <div class="relative">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    ref="inputRef"
                    v-model="searchQuery"
                    placeholder="Search or add bloodline..."
                    class="pl-9 uppercase"
                    @focus="handleFocus"
                    @keydown.enter.prevent="canCreateNew ? createBloodline() : (filteredBloodlines.length === 1 ? selectBloodline(filteredBloodlines[0]) : null)"
                />
            </div>

            <!-- Dropdown -->
            <div
                v-if="showDropdown"
                ref="dropdownRef"
                class="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-lg max-h-60 overflow-auto"
            >
                <!-- Available bloodlines -->
                <div v-if="filteredBloodlines.length > 0" class="p-1">
                    <button
                        v-for="bloodline in filteredBloodlines"
                        :key="bloodline.id"
                        @click="selectBloodline(bloodline)"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-accent rounded-sm transition-colors"
                    >
                        {{ bloodline.name }}
                    </button>
                </div>

                <!-- No matches message -->
                <div v-else-if="searchQuery && !canCreateNew" class="p-3 text-sm text-muted-foreground text-center">
                    No bloodlines found
                </div>

                <!-- Create new option -->
                <div v-if="canCreateNew" class="p-1 border-t">
                    <button
                        @click="createBloodline"
                        :disabled="isCreating"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-accent rounded-sm transition-colors flex items-center gap-2 text-primary"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Create "{{ searchQuery.toUpperCase() }}"</span>
                    </button>
                </div>

                <!-- Manage bloodlines link -->
                <div class="p-1 border-t">
                    <button
                        @click="openManageModal"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-accent rounded-sm transition-colors text-muted-foreground"
                    >
                        Manage bloodlines...
                    </button>
                </div>
            </div>
        </div>

        <!-- Helper text -->
        <p class="text-xs text-muted-foreground">
            Select multiple bloodlines. Click the star to set the primary bloodline.
        </p>

        <!-- Manage Bloodlines Modal -->
        <Dialog v-model:open="showManageModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Manage Bloodlines</DialogTitle>
                    <DialogDescription>
                        Edit or delete your bloodlines. Bloodlines in use cannot be deleted.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-80 overflow-auto">
                    <div v-if="manageError" class="mb-3 p-2 bg-destructive/10 text-destructive text-sm rounded">
                        {{ manageError }}
                    </div>

                    <div v-if="bloodlines.length === 0" class="py-4 text-center text-muted-foreground">
                        No bloodlines yet. Create one by typing in the search field.
                    </div>

                    <div v-else class="space-y-1">
                        <div
                            v-for="bloodline in bloodlines"
                            :key="bloodline.id"
                            class="flex items-center gap-2 p-2 rounded hover:bg-accent/50"
                        >
                            <template v-if="editingBloodline?.id === bloodline.id">
                                <Input
                                    v-model="editName"
                                    class="flex-1 uppercase"
                                    @keydown.enter="saveEdit"
                                    @keydown.escape="cancelEditing"
                                    autofocus
                                />
                                <Button size="sm" variant="ghost" @click="saveEdit">
                                    <Check class="h-4 w-4" />
                                </Button>
                                <Button size="sm" variant="ghost" @click="cancelEditing">
                                    <X class="h-4 w-4" />
                                </Button>
                            </template>
                            <template v-else>
                                <span class="flex-1">{{ bloodline.name }}</span>
                                <Button size="sm" variant="ghost" @click="startEditing(bloodline)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button size="sm" variant="ghost" @click="deleteBloodline(bloodline)">
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showManageModal = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
