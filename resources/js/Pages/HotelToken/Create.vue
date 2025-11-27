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
    code: '',
    type: '',
    token: {
        url: '',
        // 3t
        api_key: '',
        login: '',
        password: '',
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
    form.post(route('hotel-tokens.store'))
}
</script>

<template>
    <AppLayout title="Create Hotel Token">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Hotel Token
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <FormSection @submitted="store">
                <template #title>
                    Create Hotel Token
                </template>

                <template #description>
                    <p>This data is used by api router, insure that token code is unique, there is one code for each
                        token.</p>
                </template>

                <template #form>
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" ref="name" v-model="form.name" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="iata" value="Token Code" />
                        <TextInput id="iata" ref="iata" v-model="form.code" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.iata" class="mt-2" />
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="type" value="Type" />
                        <select v-model="form.type" class="mt-1 block w-3/4">
                            <option value="" disabled>Select Option</option>
                            <option value="3t">3T hotel token</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>


                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="url" value="Url" />
                        <TextInput id="url" ref="url" v-model="form.token.url" type="url" class="mt-1 block w-full" />
                        <InputError :message="form.errors?.token?.url" class="mt-2" />
                    </div>
                    <template v-if="form.type == '3t'">
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="api_key" value="API Key" />
                            <TextInput id="api_key" ref="api_key" v-model="form.token.api_key" type="text"
                                class="mt-1 block w-full" />
                            <InputError :message="form.errors?.token?.api_key" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="login" value="Login" />
                            <TextInput id="login" ref="login" v-model="form.token.login" type="text"
                                class="mt-1 block w-full" />
                            <InputError :message="form.errors?.token?.login" class="mt-2" />
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="api_key" value="Password" />
                            <TextInput id="password" ref="password" v-model="form.token.password" type="text"
                                class="mt-1 block w-full" />
                            <InputError :message="form.errors?.token?.password" class="mt-2" />
                        </div>

                    </template>





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
