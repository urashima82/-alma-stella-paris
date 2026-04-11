import controller_0 from "../../controllers/hello_controller.js";
import controller_1 from "../../controllers/lightbox_controller.js";
import controller_2 from "../../controllers/stripe_payment_controller.js";
import controller_3 from "../../controllers/mobile_menu_controller.js";
import controller_4 from "../../controllers/cart_drawer_controller.js";
import controller_5 from "../../controllers/currency_selector_controller.js";
export const eagerControllers = {"hello": controller_0, "lightbox": controller_1, "stripe-payment": controller_2, "mobile-menu": controller_3, "cart-drawer": controller_4, "currency-selector": controller_5};
export const lazyControllers = {"csrf-protection": () => import("../../controllers/csrf_protection_controller.js")};
export const isApplicationDebug = true;