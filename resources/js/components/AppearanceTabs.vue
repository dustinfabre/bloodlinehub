<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';

const { appearance } = useAppearance();
</script>

<template>
    <div class="rounded-lg border border-blue-100/70 bg-white p-4 text-sm text-slate-600">
        <p class="font-medium text-slate-800">Theme</p>
        <p class="mt-1">
            Light mode is enforced for all users. Current theme: <strong>{{ appearance }}</strong>.
        </p>
    </div>
</template>
