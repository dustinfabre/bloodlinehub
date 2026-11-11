<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BetaNotice from '@/components/BetaNotice.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import PedigreePrivacyPrompt from '@/components/pedigree/PedigreePrivacyPrompt.vue';
import PwaStatus from '@/components/PwaStatus.vue';
import TrialNotice from '@/components/TrialNotice.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <TrialNotice />
            <BetaNotice />
            <slot />
        </AppContent>
        <MobileBottomNav />
        <PwaStatus />
        <PedigreePrivacyPrompt />
        <Toaster
            position="top-right"
            :richColors="true"
            :closeButton="true"
            :duration="3000"
        />
    </AppShell>
</template>
