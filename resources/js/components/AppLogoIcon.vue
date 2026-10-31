<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img
        src="/icons/logo.webp"
        alt="BloodlineHub"
        draggable="false"
        :class="['object-contain select-none', className]"
        v-bind="$attrs"
    />
</template>
