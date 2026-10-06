@if (
    request()->routeIs('shop.checkout.onepage.index')
    && (bool) core()->getConfigData('sales.payment_methods.north.active')
)
    @pushOnce('scripts')
        <script>
            /**
             * A promise other scripts can await instead of guessing whether checkout.js has
             * finished loading, so session creation and script download can finish in either order.
             */
            window.northCheckoutReady = new Promise((resolve) => {
                window.resolveNorthCheckoutReady = resolve;
            });

            /**
             * A page restored from the back/forward cache reuses a stale checkout.js and session
             * state from a prior transaction, so force a fresh load instead of reusing it.
             */
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>

        <script
            src="https://checkout.north.com/checkout.js"
            onload="window.resolveNorthCheckoutReady()"
        ></script>

        <script
            type="text/x-template"
            id="v-north-form-template"
        >
            <x-shop::modal
                :is-active="true"
                panel-class="north-form-modal-panel"
                @toggle="onModalToggle"
            >
                <x-slot:header>
                    @lang('shop::app.checkout.onepage.summary.place-order')
                </x-slot:header>

                <x-slot:content>
                    <div
                        v-if="isLoading"
                        class="flex w-full items-center justify-center"
                        style="min-height: 480px;"
                    >
                        <svg
                            class="text-blue h-8 w-8 animate-spin text-[5px] font-semibold"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </div>

                    <div
                        v-show="! isLoading"
                        id="north-form-container"
                        class="relative w-full"
                    ></div>
                </x-slot:content>
            </x-shop::modal>
        </script>

        <style>
            /**
             * North's blade files sit outside Shop's Tailwind content scan, so an arbitrary-value
             * utility class here (e.g. max-w-[...]) never gets compiled and silently does nothing.
             * Size this modal with hand-written CSS instead.
             */
            .north-form-modal-panel {
                max-width: 720px !important;
            }

            #north-form-container {
                min-height: 480px;
            }

            /**
             * North's hosted iframe ships its own fixed, corner-anchored inline styles;
             * override them so it fills this modal's content area instead.
             */
            #north-form-container iframe {
                position: static !important;
                width: 100% !important;
                height: 100% !important;
                min-height: 480px !important;
                border: 0 !important;
            }
        </style>

        <script type="module">
            app.component('v-north-form', {
                template: '#v-north-form-template',

                data() {
                    return {
                        sessionToken: null,

                        unsubscribe: null,

                        isLoading: true,
                    };
                },

                mounted() {
                    this.createSession();
                },

                beforeUnmount() {
                    this.forgetSession();
                },

                methods: {
                    /**
                     * Open a North session for the current cart, then mount once checkout.js is ready.
                     */
                    createSession() {
                        this.$axios.post("{{ route('shop.north.create-session') }}")
                            .then(response => {
                                this.sessionToken = response.data.token;

                                return window.northCheckoutReady;
                            })
                            .then(() => this.mountForm())
                            .catch(error => {
                                this.isLoading = false;

                                this.$emitter.emit('add-flash', { type: 'error', message: '@lang('north::app.errors.invalid-configs')' });

                                this.forgetSession();
                            });
                    },

                    /**
                     * Mount North's hosted card inputs, which carry their own submit control,
                     * and subscribe to the payment result.
                     */
                    mountForm() {
                        checkout.mount(this.sessionToken, 'north-form-container');

                        this.unsubscribe = checkout.onPaymentComplete(() => this.verify());

                        this.isLoading = false;
                    },

                    /**
                     * Treat the modal being dismissed as a cancelled payment, same as other gateways.
                     */
                    onModalToggle(event) {
                        if (event.isActive) {
                            return;
                        }

                        this.$emitter.emit('add-flash', { type: 'error', message: '@lang('north::app.response.payment-cancelled')' });

                        this.forgetSession();
                    },

                    /**
                     * Discard this transaction's session and listener so nothing from it survives
                     * into a retry or a later, unrelated transaction.
                     */
                    forgetSession() {
                        try {
                            if (this.unsubscribe) {
                                this.unsubscribe();
                            }
                        } catch (error) {
                            /**
                             * North's unsubscribe callback failing must never block the caller.
                             */
                        }

                        this.sessionToken = null;

                        this.unsubscribe = null;
                    },

                    /**
                     * Ask the server to confirm the session's status and place the order.
                     */
                    verify() {
                        this.$axios.post("{{ route('shop.north.verify') }}", {
                            _token: "{{ csrf_token() }}",
                            session_token: this.sessionToken,
                        })
                            .then(response => {
                                if (response.data.success) {
                                    window.location.href = "{{ route('shop.checkout.onepage.success') }}";

                                    return;
                                }
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: '@lang('north::app.errors.something-went-wrong')' });

                                this.forgetSession();
                            });
                    },
                },
            });
        </script>
    @endPushOnce
@endif
