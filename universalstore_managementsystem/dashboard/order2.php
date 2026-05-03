<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking & History</title>
    <link href="../assets/css/tailwind.css" rel="stylesheet">
    <style>
        .step-active { color: #2563eb; font-weight: 600; }
        .step-line { position: relative; }
        .step-line::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 1.25rem;
            bottom: -1.25rem;
            width: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        .step-line:last-child::after { display: none; }
        .dot { position: relative; z-index: 10; }
        .details-panel { display: none; }
        .details-panel.open { display: block; }
    </style>
</head>
<body class="bg-slate-50 p-4 md:p-12">

    <div class="max-w-5xl mx-auto">
        <header class="mb-10">
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Your Orders</h1>
            <p class="text-slate-500 mt-2">Track, return, or buy items again.</p>
        </header>

        <!-- Order Card -->
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm transition-all hover:shadow-md">
            <!-- Header Info -->
            <div class="bg-slate-50 border-b border-slate-200 p-6 flex flex-wrap justify-between items-center gap-4">
                <div class="flex gap-10">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400 tracking-widest">Order Placed</p>
                        <p class="text-sm font-medium text-slate-700">January 4, 2024</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400 tracking-widest">Total</p>
                        <p class="text-sm font-medium text-slate-700">45,000 XAF</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-400 tracking-widest">Ship To</p>
                        <p class="text-sm font-medium text-blue-600 cursor-pointer hover:underline">Jean Emmanuel</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase font-bold text-slate-400 tracking-widest text-right">Order #</p>
                    <p class="text-sm font-medium text-slate-700">ORD-5542091</p>
                </div>
            </div>

            <div class="p-6">
                <div class="flex flex-col lg:flex-row gap-10">
                    <!-- Left: Progress Timeline -->
                    <div class="w-full lg:w-1/3">
                        <h4 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                            Delivery Status
                        </h4>
                        <div class="space-y-6">
                            <div class="step-line flex gap-4">
                                <div class="dot w-5 h-5 bg-blue-600 rounded-full border-4 border-blue-100 mt-1"></div>
                                <div>
                                    <p class="text-sm font-semibold text-blue-600">Processing</p>
                                    <p class="text-xs text-slate-400">Jan 5, 09:24 AM</p>
                                </div>
                            </div>
                            <div class="step-line flex gap-4">
                                <div class="dot w-5 h-5 bg-slate-200 rounded-full mt-1"></div>
                                <div>
                                    <p class="text-sm font-medium text-slate-400">Shipped</p>
                                    <p class="text-xs text-slate-300">Expected Jan 7</p>
                                </div>
                            </div>
                            <div class="step-line flex gap-4">
                                <div class="dot w-5 h-5 bg-slate-200 rounded-full mt-1"></div>
                                <div>
                                    <p class="text-sm font-medium text-slate-400">Delivered</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Product List -->
                    <div class="flex-1">
                        <div class="flex items-start gap-6 pb-6 border-b border-slate-100 last:border-0">
                            <div class="w-24 h-24 bg-slate-100 rounded-xl flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-bold text-slate-800 text-lg">MacBook Pro 14" M2</h3>
                                    <span class="text-lg font-bold">1,200,000 XAF</span>
                                </div>
                                <p class="text-sm text-slate-500 mt-1 leading-relaxed">Space Gray | 16GB RAM | 512GB SSD. Items are being inspected before shipping.</p>
                                <div class="mt-4 flex gap-3">
                                    <button class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition">Track Package</button>
                                    <button class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-50 transition">View Item</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-slate-50 px-6 py-4 flex justify-between items-center border-t border-slate-100">
                <p class="text-sm text-slate-500">Need help with this order? <a href="#" class="text-blue-600 font-medium">Contact Support</a></p>
                <button class="text-sm font-bold text-slate-700 hover:text-slate-900">Download Invoice (PDF)</button>
            </div>
        </div>
    </div>

</body>
</html><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status System</title>
    <link href="assets/css/tailwind.css" rel="stylesheet">
    <style>
        .timeline-step { position: relative; }
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -20px;
            width: 2px;
            background: #e2e8f0;
        }
        .active-step::after { background: #3b82f6 !important; }
    </style>
</head>
<body class="bg-gray-50 text-slate-900 p-6 md:p-12">

    <div class="max-w-4xl mx-auto">
        <header class="mb-10 text-center">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Order Settlement Engine</h1>
            <p class="text-slate-500 mt-2">A breakdown of the lifecycle from intent to final settlement.</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Left Column: Process Flow -->
            <div class="md:col-span-1 space-y-10">
                <div class="timeline-step active-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-lg shadow-blue-200">1</div>
                        <div>
                            <h3 class="font-bold">Placement</h3>
                            <p class="text-xs text-slate-400">Pending / Paid</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step active-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-lg shadow-blue-200">2</div>
                        <div>
                            <h3 class="font-bold">Fulfillment</h3>
                            <p class="text-xs text-slate-400">Processing / On Hold</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold">3</div>
                        <div>
                            <h3 class="font-bold">Logistics</h3>
                            <p class="text-xs text-slate-400">Shipped / Out for Delivery</p>
                        </div>
                    </div>
                </div>

                <div class="timeline-step">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center font-bold">4</div>
                        <div>
                            <h3 class="font-bold">Settlement</h3>
                            <p class="text-xs text-slate-400">Delivered / Completed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Detail Cards -->
            <div class="md:col-span-2 space-y-4">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-4">
                        <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full uppercase tracking-wider">Current State: Processing</span>
                        <span class="text-slate-400 text-sm">Ref: #ORD-9921</span>
                    </div>
                    <h2 class="text-xl font-bold mb-2">Detailed Status Definitions</h2>
                    <ul class="space-y-4 mt-6">
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">On Hold</p>
                                <p class="text-sm text-slate-500">Manual intervention required. Common for international shipping or high-risk payments.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-green-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Delivered (Settled)</p>
                                <p class="text-sm text-slate-500">The courier has confirmed receipt. The revenue is now officially earned.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div class="w-2 h-2 rounded-full bg-red-500 mt-2"></div>
                            <div>
                                <p class="font-bold text-sm">Cancelled (Settled)</p>
                                <p class="text-sm text-slate-500">The transaction is dead. Stock is returned to the virtual shelf.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="bg-blue-600 p-6 rounded-2xl text-white shadow-xl shadow-blue-100">
                    <h3 class="font-bold mb-2">Pro-Tip for Merchants</h3>
                    <p class="text-sm opacity-90 leading-relaxed">
                        Always distinguish between "Delivered" and "Completed." This allows your accounting team to track potential refund liabilities during the return window.
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>