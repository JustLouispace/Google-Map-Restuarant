<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form @submit.prevent="submitSearch" class="d-flex">
        <input
          type="text"
          class="form-control me-2"
          placeholder="Search restaurants (e.g., Bang sue)"
          v-model="term"
          aria-label="Search"
        />
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search me-1"></i> Search
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  initialValue: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['search']);
const term = ref(props.initialValue);

const submitSearch = () => {
  emit('search', term.value);
};
</script>