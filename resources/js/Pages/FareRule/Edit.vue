<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { ref } from 'vue';

const props = defineProps({
    fare_rule: Object,
    fare_note: String
})


const form = useForm({
   aero_token_id: props.fare_rule?.aero_token_id,
   carrier: props.fare_rule.carrier,
   fare_id: props.fare_rule.fare_id,
   rules: props.fare_rule.rules,
   note: props.fare_rule.note,
   status: props.fare_rule.status,
});

const itemForm = useForm({
   fare_rule_id: props.fare_rule?.id,
 key: '',
 value: '',
 status: '',
 note: ''
});

const storeFareRule = () => {
    form.put(route('fare-rules.update', {fare_rule: props.fare_rule.id}))
}

const storeFareRuleItems = () => {
    itemForm.post(route('fare-rules.items.store'), {
        onFinish: ()=> {
            closeModal()
        }
    })
}

const itemsModal = ref(false)
const closeModal = () => {
    itemsModal.value = false;

    itemForm.key = '';
    itemForm.value = '';
    itemForm.status = '';
    itemForm.note = '';
    itemForm.error = '';
};
</script>

<template>
    <AppLayout title="Edit Fare Rule">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Fare Rule
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <FormSection @submitted="storeFareRule">
                <template #title>
                    Edit Fare Rule
                </template>

                <template #description>
                    <p>This provide informations about fare rule.</p>

                    <br>
                    <p>Fare Note:</p>
                    <p>{{  props.fare_note }}</p>
                </template>

                <template #form>

                     <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="carrier" value="Carrier" />
                        <TextInput
                            id="carrier"
                            ref="carrier"
                            readonly
                            v-model="form.carrier"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.carrier" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="fare_id" value="Fare ID" />
                        <TextInput
                            id="fare_id"
                            ref="fare_id"
                            readonly
                            v-model="form.fare_id"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.fare_id" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="rules" value="Rules" />
                        {{ form.rules }}
                        <!-- <TextInput
                            id="rules"
                            ref="rules"
                            v-model="form.rules"
                            type="text"
                            class="mt-1 block w-full"
                        /> -->
                        <InputError :message="form.errors.rules" class="mt-2" />
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="note" value="Note" />
                        <TextInput
                            id="note"
                            ref="note"
                            v-model="form.note"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.note" class="mt-2" />
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="status" value="Status" />
                        <select
                            v-model="form.status"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Status</option>
                            <option value="new" >New</option>
                            <option value="changed" >Changed</option>
                            <option value="updated" >Updated</option>
                        </select>
                      
                        <InputError :message="form.errors.status" class="mt-2" />
                    </div>

                </template>

                <template #actions>
                    <ActionMessage :on="form.recentlySuccessful" class="me-3">
                        Saved.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </template>
            </FormSection>

            <FormSection @submitted="storeFareRuleItems" class="mt-3">
                <template #title>
                    Fare Rule Items
                </template>

                <template #description>
                    <p>This section is an extraction of fare rule details as items.</p>

                    <br>
                    <p>Fare Note:</p>
                    <p>{{  props.fare_note }}</p>
                </template>

                <template #form>
                    <PrimaryButton @click.prevent="itemsModal = true">
                        Add Item
                    </PrimaryButton>
                    <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700 col-span-12">
                        <li class="py-3 sm:py-4" v-for="(item,index) in fare_rule.items">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <p class="text-sm font-medium text-gray-900 truncate dark:text-white">
                                       {{ item.key}}
                                    </p>
                                    <p class="text-sm text-gray-500 truncate dark:text-gray-400">
                                        {{ item.value}}
                                    </p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span>{{ item.note }}</span>
                                </div>
                               
                                
                                <div class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white">
                                    <span>{{ item.status }}</span>
                                </div>
                                <SecondaryButton >
                                    Edit
                                </SecondaryButton>
                                <PrimaryButton >
                                    Remove
                                </PrimaryButton>
                            </div>
                        </li>
                    </ul>

                </template>

            </FormSection>
        </div>

        <!--  -->
        <DialogModal :show="itemsModal" @close="closeModal">
            <template #title>
                Add Item
            </template>

            <template #content>

                <div class="mt-4">
                    <div class="mb-1">
                        <select
                            v-model="itemForm.key"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Option</option>
                            <option value="refund" >Refund</option>
                            <option value="change" >Change</option>
                            <option value="upgrade" >Upgrade</option>
                            <option value="noshow" >No-Show</option>
                            <option value="baggage" >Baggage</option>
                        </select>
                    </div>
                    
                    <div class="mb-1">
                        <TextInput
                            v-model="itemForm.value"
                            type="text"
                            class="mt-1 block w-3/4"
                            placeholder="Value"
                        />
                        <InputError :message="form.error" class="mt-2" />
                    </div>

                    <div class="mb-1">
                        <select
                            v-model="itemForm.status"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Status</option>
                            <option value="available" >Available</option>
                            <option value="unavailable" >Unavailable</option>
                            <option value="restricted" >Restricted</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <textarea
                            v-model="itemForm.note"
                            class="mt-1 block w-3/4"
                            placeholder="Note"
                        ></textarea>
                    </div>

                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="closeModal">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': itemForm.processing }"
                    :disabled="itemForm.processing"
                    @click="storeFareRuleItems"
                >
                    Save
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
