<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
  links: {
    type: Array,
    required: true,
  },
});

const classes = "mr-1 px-4 py-3 text-sm leading-4 border rounded";
</script>

<template>
  <!-- If more than (prev, 1, next) -->
  <div v-if="links.length > 3">
    <div class="flex flex-wrap mt-4">
      <template v-for="link in links" :key="key">
        <div
          v-if="link.url === null"
          class="text-gray-400 cursor-not-allowed"
          :class="classes"
          v-html="link.label"
          aria-disabled="true"
        />
        <Link
          v-else
          class="hover:bg-black hover:text-white focus:border-indigo-500 focus:text-indigo-500"
          :class="[classes, { 'bg-black text-white': link.active }]"
          :href="link.url"
          v-html="link.label"
          aria-disabled="false"
          preserve-scroll
        />
      </template>
    </div>
  </div>
</template>