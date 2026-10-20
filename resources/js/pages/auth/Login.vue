<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const demoEmail = ref('');
const demoPassword = ref('');

function fillDemo(email: string) {
    demoEmail.value = email;
    demoPassword.value = 'password';
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <!-- Quick Demo Account Selector -->
    <div class="mb-4 rounded-xl bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-3 text-xs">
        <span class="font-bold text-slate-700 dark:text-zinc-300 block mb-2">⚡ Pilih Akun Demo (1-Klik):</span>
        <div class="grid grid-cols-2 gap-1.5">
            <button
                type="button"
                @click="fillDemo('admin@gymapp.test')"
                class="px-2.5 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 font-bold hover:bg-indigo-100 dark:hover:bg-indigo-900/40 text-left transition cursor-pointer"
            >
                👑 Owner (BI)
            </button>
            <button
                type="button"
                @click="fillDemo('frontdesk@gymapp.test')"
                class="px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-bold hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-left transition cursor-pointer"
            >
                🖥️ Front Desk / POS
            </button>
            <button
                type="button"
                @click="fillDemo('trainer@gymapp.test')"
                class="px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-bold hover:bg-amber-100 dark:hover:bg-amber-900/40 text-left transition cursor-pointer"
            >
                🏋️ Trainer (PT)
            </button>
            <button
                type="button"
                @click="fillDemo('member1@gymapp.test')"
                class="px-2.5 py-1.5 rounded-lg bg-lime-50 dark:bg-lime-950/40 text-lime-700 dark:text-[#A3E635] border border-lime-200 dark:border-lime-800 font-bold hover:bg-lime-100 dark:hover:bg-lime-900/40 text-left transition cursor-pointer"
            >
                📱 Member Portal
            </button>
        </div>
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    v-model="demoEmail"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    v-model="demoPassword"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>
</template>
