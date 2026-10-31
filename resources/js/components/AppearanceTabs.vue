<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';

const { appearance } = useAppearance();
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">
        <p class="font-medium text-foreground">Theme</p>
        <p class="mt-1">
            Light mode is enforced for all users. Current theme: <strong>{{ appearance }}</strong>.
        </p>
    </div>
</template>
