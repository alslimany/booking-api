<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({

})

const form = useForm({
    name: '',
    iata: '',
    type: '',
    token: {
        url: '',
        // videcom
        api_token: '',
        // amadeus
        api_key: '',
        api_secret: ''
    },
    pricing: {
        currency_code: 'LYD',
        profit_from: '',
        profit_percentage_international: 0,
        profit_percentage_domestic: 0,
        added_tax: 0,
    }
});

const store = () => {
    form.post(route('aero-tokens.store'))
}
</script>

<template>
    <AppLayout title="Create Aero Token">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Aero Token
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <FormSection @submitted="store">
                <template #title>
                    Create Aero Token
                </template>

                <template #description>
                    <p>This data is used by api router, insure that iata code is unique, there is one iata code for each token.</p>
                    <p class="text-red-500">The token type must be a videcom or amadeus only!</p>
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            ref="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="iata" value="IATA Code" />
                        <TextInput
                            id="iata"
                            ref="iata"
                            v-model="form.iata"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.iata" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="type" value="Type" />
                        <TextInput
                            id="type"
                            ref="type"
                            v-model="form.type"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>


                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="url" value="Url" />
                        <TextInput
                            id="url"
                            ref="url"
                            v-model="form.token.url"
                            type="url"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.token?.url" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4" v-if="form.type == 'videcom'">
                        <InputLabel for="api_token" value="API Token" />
                        <TextInput
                            id="api_token"
                            ref="api_token"
                            v-model="form.token.api_token"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.token?.api_token" class="mt-2" />
                    </div>

                    <!-- Pricing -->
                    <h4>Pricing</h4>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="currency_code" value="Currency Code" />
                        <TextInput
                            id="currency_code"
                            ref="currency_code"
                            v-model="form.pricing.currency_code"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.currency_code" class="mt-2" />
                    </div>
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_from" value="Profit From" />
                        <select
                            v-model="form.pricing.profit_from"
                            class="mt-1 block w-3/4"
                        >
                            <option value="" disabled>Select Option</option>
                            <option value="fare" >Fare Basis</option>
                            <option value="total" >Total Price</option>
                        </select>
                        <InputError :message="form.errors?.pricing?.profit_from" class="mt-2" />
                    </div>
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_percentage" value="Profit Percentage International (%)" />
                        <TextInput
                            id="profit_percentage"
                            ref="profit_percentage"
                            v-model="form.pricing.profit_percentage_international"
                            type="number"
                            setp="any"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.profit_percentage_international" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="profit_percentage" value="Profit Percentage Domestic (%)" />
                        <TextInput
                            id="profit_percentage"
                            ref="profit_percentage"
                            v-model="form.pricing.profit_percentage_domestic"
                            type="number"
                            setp="any"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.profit_percentage_domestic" class="mt-2" />
                    </div>
                    
                    <!--  -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="added_tax" value="Added Tax (%)" />
                        <TextInput
                            id="added_tax"
                            ref="added_tax"
                            v-model="form.pricing.added_tax"
                            type="number"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors?.pricing?.added_tax" class="mt-2" />
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
        </div>
    </AppLayout>
</template>
