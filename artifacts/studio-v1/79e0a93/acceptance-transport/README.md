# One-order staging acceptance transport

Updates only the separate administrator utility to0.2.0. The installed Studio candidate79e0a93 remains unchanged and is not reinstalled.

Owner authorized at most one synthetic order and no real payment. The buyer reached classic checkout with3size lines and the server totalRp240000; staging has no payment method. This utility uses the normal Studio checkout authorization and WooCommerce create_order/order-created hooks to create exactly one pending unpaid guest order from that existing cart, without invoking payment processing or updating the Owner profile. A durable pre-create claim blocks all duplicate/ambiguous retries. It is NOT evidence of a paid buyer Place-order flow.

Private DTF/PDF generation is limited to this exact authorized order, requires normal explicit production readiness and complete canonical design/order/preview integrity. Each opt-in is request-local, only after admin capability+source nonce+fresh installed gate, and refuses any flag explicitly configured OFF. No flag/config option is persisted; no Drive/payment/GC invoked. Result files and manifest remain in the verified private release root. Default/next request retains writers OFF.

Boundary test PASS: ambiguous creation attempt rejected, existing order resumed without cart/create calls, explicit DTF OFF never overridden. PHP lint PASS. Runtime acceptance pending.
