<script setup lang="ts">
//
</script>

<template>
    <div class="kiosk-root">
        <slot />
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;1,9..144,300;1,9..144,400&family=DM+Sans:wght@300;400;500;600&display=swap');

.kiosk-root {
    min-height: 100vh;
    background: #0b1a0c;
    font-family: 'DM Sans', sans-serif;
    color: #f0ebe0;
}
</style>
