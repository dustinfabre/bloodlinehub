<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

// "Hub" is ivory in this artwork, so only place it on navy or other dark surfaces
</script>

<template>
    <img
        src="/icons/long-logo.webp"
        alt="BloodlineHub"
        draggable="false"
        :class="['w-auto object-contain select-none', className]"
        v-bind="$attrs"
    />
</template>
