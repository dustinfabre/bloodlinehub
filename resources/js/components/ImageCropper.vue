<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { ref, watch, nextTick, onUnmounted } from 'vue';

interface Props {
    open: boolean;
    imageSrc: string | null;
    aspectRatio?: number;
    minWidth?: number;
    minHeight?: number;
}

const props = withDefaults(defineProps<Props>(), {
    aspectRatio: 1, // Square crop by default (for card display)
    minWidth: 200,
    minHeight: 200,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'crop': [file: File];
    'cancel': [];
}>();

const imageRef = ref<HTMLImageElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const cropper = ref<any>(null);
const isProcessing = ref(false);

// Initialize cropper when dialog opens
watch(() => props.open, async (isOpen) => {
    if (isOpen && props.imageSrc) {
        await nextTick();
        // Small delay to ensure image is loaded
        setTimeout(() => {
            initCropper();
        }, 100);
    } else {
        destroyCropper();
    }
});

const initCropper = () => {
    if (!imageRef.value) return;
    
    destroyCropper();
    
    cropper.value = new Cropper(imageRef.value, {
        aspectRatio: props.aspectRatio,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.9,
        restore: false,
        guides: true,
        center: true,
        highlight: true,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        minCropBoxWidth: 100,
        minCropBoxHeight: 100,
        responsive: true,
    });
};

const destroyCropper = () => {
    if (cropper.value) {
        cropper.value.destroy();
        cropper.value = null;
    }
};

const handleCrop = async () => {
    if (!cropper.value) return;
    
    isProcessing.value = true;
    
    try {
        const canvas = cropper.value.getCroppedCanvas({
            width: props.minWidth * 2, // Higher quality output
            height: props.minHeight * 2,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        
        // Convert canvas to blob
        canvas.toBlob((blob: Blob | null) => {
            if (blob) {
                const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
                emit('crop', file);
                closeDialog();
            }
            isProcessing.value = false;
        }, 'image/jpeg', 0.9);
    } catch (error) {
        console.error('Error cropping image:', error);
        isProcessing.value = false;
    }
};

const handleCancel = () => {
    emit('cancel');
    closeDialog();
};

const closeDialog = () => {
    destroyCropper();
    emit('update:open', false);
};

// Rotation and zoom controls
const rotateLeft = () => {
    cropper.value?.rotate(-90);
};

const rotateRight = () => {
    cropper.value?.rotate(90);
};

const zoomIn = () => {
    cropper.value?.zoom(0.1);
};

const zoomOut = () => {
    cropper.value?.zoom(-0.1);
};

const reset = () => {
    cropper.value?.reset();
};

onUnmounted(() => {
    destroyCropper();
});
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <DialogHeader>
                <DialogTitle>Crop Image</DialogTitle>
            </DialogHeader>
            
            <div class="flex-1 min-h-0 overflow-hidden">
                <!-- Cropper container -->
                <div class="relative w-full h-[50vh] sm:h-[60vh] bg-muted rounded-lg overflow-hidden">
                    <img 
                        ref="imageRef"
                        :src="imageSrc || ''" 
                        alt="Image to crop"
                        class="max-w-full max-h-full"
                        style="display: block;"
                    />
                </div>
                
                <!-- Controls -->
                <div class="flex items-center justify-center gap-2 mt-4 flex-wrap">
                    <Button type="button" variant="outline" size="sm" @click="rotateLeft" title="Rotate left">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.5 2v6h6M2.66 15.57a10 10 0 1 0 .57-8.38"/>
                        </svg>
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="rotateRight" title="Rotate right">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/>
                        </svg>
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="zoomOut" title="Zoom out">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/>
                        </svg>
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="zoomIn" title="Zoom in">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/>
                        </svg>
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="reset" title="Reset">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                        </svg>
                    </Button>
                </div>
            </div>
            
            <DialogFooter class="gap-2 sm:gap-0 mt-4">
                <Button variant="outline" type="button" @click="handleCancel" :disabled="isProcessing">
                    Cancel
                </Button>
                <Button type="button" @click="handleCrop" :disabled="isProcessing">
                    <span v-if="isProcessing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                    <span v-else>Apply Crop</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style>
/* Ensure cropper styles work properly */
.cropper-container {
    width: 100% !important;
    height: 100% !important;
}

.cropper-modal {
    background-color: rgba(0, 0, 0, 0.7);
}

.cropper-view-box {
    outline: 1px solid hsl(var(--primary));
    outline-color: rgba(255, 255, 255, 0.75);
}

.cropper-line {
    background-color: hsl(var(--primary));
}

.cropper-point {
    background-color: hsl(var(--primary));
    width: 10px;
    height: 10px;
    opacity: 1;
}

.cropper-point.point-se {
    width: 14px;
    height: 14px;
}
</style>
