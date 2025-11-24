/**
 * WooCommerce Sample Orders - Admin Script
 * v2: Countries/States + Shipping fields + existing features
 */
jQuery(document).ready(function ($) {
  "use strict";

  // ========= State =========
  let selectedProducts = [];
  let productsCache = null;
  let searchTimeout;

  const CACHE_KEY = "wcso_products_cache";
  const CACHE_TIMESTAMP_KEY = "wcso_products_cache_timestamp";
  const CACHE_DURATION = 24 * 60 * 60 * 1000; // 24h

  const config = {
    enableScanner: wcsoData.enableScanner === "1",
    ajaxUrl: wcsoData.ajaxUrl,
    nonces: {
      search: wcsoData.searchNonce,
      cache: wcsoData.cacheNonce,
      order: wcsoData.orderNonce,
    },
  };

  // ========= Init =========
  function init() {
    loadProductCache();
    bindEvents();
    updateProductsTable();
    if (config.enableScanner) $("#barcode_input").focus();
    populateCountries(); // hydrate shipping country/state
  }

  // ========= Events =========
  function bindEvents() {
    // Cache
    $("#refresh-cache").on("click", handleRefreshCache);

    // Search
    $("#product_search").on("keyup", handleProductSearch);
    $(document).on("click", ".wcso-dropdown-item", handleProductSelect);
    $(document).on("click", handleDropdownClose);

    // Scanner
    if (config.enableScanner) {
      $("#barcode_input").on("keydown", handleBarcodeKeydown);
      $("#barcode_input").on("input", handleBarcodeInput);
    }

    // Quantity & remove
    $(document).on("click", ".wcso-qty-decrease", handleQtyDecrease);
    $(document).on("click", ".wcso-qty-increase", handleQtyIncrease);
    $(document).on("change", ".wcso-qty-input", handleQtyChange);
    $(document).on("click", ".wcso-remove-btn", handleRemoveProduct);

    // Modal
    $(document).on("click", ".wcso-modal-item", handleModalProductSelect);
    $("#close_modal").on("click", closeModal);

    // Country/State
    $(document).on("change", "#shipping_country", populateStates);

    // Submit
    $("#wcso-order-form").on("submit", handleFormSubmit);
  }

  // ========= Countries / States =========
  function populateCountries() {
    const $country = $("#shipping_country");
    if (!$country.length) return;
    $country.empty();

    // console.log("wcsoData:", wcsoData);
    // console.log("typeof wcsoData:", typeof wcsoData);

    const countries = wcsoData.countries || {};

    // console.log("Countries:", countries);
    Object.keys(countries).forEach((code) => {
      $country.append(new Option(countries[code], code));
    });
    if (wcsoData.baseCountry && countries[wcsoData.baseCountry])
      $country.val(wcsoData.baseCountry);
    populateStates();
    // populateShippingMethods();
  }

  function populateStates() {
    const $country = $("#shipping_country");
    const $state = $("#shipping_state");
    if (!$country.length || !$state.length) return;

    const cc = $country.val();

    console.log("cc:", cc);
    const all = wcsoData.states || {};
    const map = all[cc] || {};
    const has = Object.keys(map).length > 0;

    if ($("#shipping_state_text").length) $("#shipping_state_text").remove();

    if (has) {
      $state.show().prop("disabled", false).empty();
      Object.keys(map).forEach((code) =>
        $state.append(new Option(map[code], code))
      );
      if (
        wcsoData.baseCountry === cc &&
        wcsoData.baseState &&
        map[wcsoData.baseState]
      ) {
        $state.val(wcsoData.baseState);
      }
    } else {
      $state.hide().prop("disabled", true).empty();
      $("<input>", {
        id: "shipping_state_text",
        class: "wcso-input",
        placeholder: "State / District",
      }).insertAfter("#shipping_state");
    }
    populateShippingMethods();
  }

  function populateShippingMethods() {
    const $country = $("#shipping_country");
    const $shippingMethod = $("#shipping_method");

    if (!$country.length || !$shippingMethod.length) return;

    const selectedCountry = $country.val();

    console.log("Selected Country:", selectedCountry);

    $shippingMethod.empty();

    const shippingZones = wcsoData.shippingZones || [];

    console.log("All Shipping Zones:", shippingZones);

    let matchedZone = null;

    for (let zone of shippingZones) {
      const zoneLocations = zone.zone_locations || [];

      for (let location of zoneLocations) {
        if (location.type === "country" && location.code === selectedCountry) {
          matchedZone = zone;
          break;
        }
      }

      if (matchedZone) break;
    }

    if (!matchedZone) {
      for (let zone of shippingZones) {
        const zoneLocations = zone.zone_locations || [];
        for (let location of zoneLocations) {
          if (location.type === "continent" && location.code === "NA") {
            matchedZone = zone;
            break;
          }
        }
        if (matchedZone) break;
      }
    }

    console.log("Matched Zone:", matchedZone);

    if (matchedZone && matchedZone.shipping_methods) {
      matchedZone.shipping_methods.forEach((method) => {
        if (method.enabled === "yes") {
          const label = method.instance_title + " - " + method.instance_cost;
          const option = new Option(label, method.method_id);

          $(option).data("methodData", {
            id: method.id,
            instance_id: method.instance_id,
            method_id: method.method_id,
            title: method.instance_title,
            cost: method.instance_cost,
          });

          $shippingMethod.append(option);
        }
      });
    }

    if ($shippingMethod.find("option").length === 0) {
      $shippingMethod.append(new Option("No shipping methods available", ""));
    }
  }
  // ========= Cache =========
  function loadProductCache() {
    const cached = localStorage.getItem(CACHE_KEY);
    const ts = localStorage.getItem(CACHE_TIMESTAMP_KEY);
    const now = Date.now();

    if (cached && ts && now - parseInt(ts, 10) < CACHE_DURATION) {
      productsCache = JSON.parse(cached);
      updateCacheStatus(
        "Loaded " + productsCache.length + " products (cached)",
        "success"
      );
      $("#search-mode")
        .text("(instant search)")
        .addClass("wcso-badge wcso-badge-publish");
    } else {
      updateCacheStatus("Loading products...", "info");
      fetchAndCacheProducts();
    }
  }

  function fetchAndCacheProducts() {
    $("#cache-loading").show();
    $("#refresh-cache").prop("disabled", true);

    $.post(
      config.ajaxUrl,
      { action: "wcso_get_all_products", nonce: config.nonces.cache },
      function (resp) {
        $("#cache-loading").hide();
        $("#refresh-cache").prop("disabled", false);
        if (resp && resp.success) {
          productsCache = resp.data || [];
          try {
            localStorage.setItem(CACHE_KEY, JSON.stringify(productsCache));
            localStorage.setItem(CACHE_TIMESTAMP_KEY, Date.now().toString());
            updateCacheStatus(
              "Loaded " + productsCache.length + " products (fresh)",
              "success"
            );
            $("#search-mode")
              .text("(instant search)")
              .addClass("wcso-badge wcso-badge-publish");
          } catch (e) {
            updateCacheStatus("Storage full! Using server search", "error");
            $("#search-mode")
              .text("(server search)")
              .addClass("wcso-badge wcso-badge-warning");
            productsCache = null;
          }
        } else {
          updateCacheStatus("Failed to load products", "error");
        }
      }
    );
  }

  function handleRefreshCache() {
    fetchAndCacheProducts();
  }

  function updateCacheStatus(msg, type) {
    const colors = {
      success: "#46b450",
      warning: "#ffb900",
      error: "#dc3232",
      info: "#2271b1",
    };
    $("#cache-status")
      .show()
      .css("border-left-color", colors[type] || "#2271b1");
    $("#cache-info").text(msg);
  }

  // ========= Search =========
  function handleProductSearch() {
    clearTimeout(searchTimeout);
    const term = $(this).val().trim();
    if (term.length < 2) return hideDropdown();

    if (productsCache) {
      const results = searchInCache(term);
      displayDropdown(results);
    } else {
      searchTimeout = setTimeout(
        () => searchServer(term, displayDropdown),
        300
      );
    }
  }

  function searchInCache(term) {
    const t = term.toLowerCase();
    const isNum = /^\d+$/.test(term);
    return (productsCache || [])
      .filter((p) => {
        if (isNum && String(p.id) === term) return true;
        if (p.sku && p.sku.toLowerCase().includes(t)) return true;
        if (p.name && p.name.toLowerCase().includes(t)) return true;
        return false;
      })
      .slice(0, 20);
  }

  function searchServer(term, cb) {
    $.post(
      config.ajaxUrl,
      {
        action: "wcso_search_products",
        search: term,
        nonce: config.nonces.search,
      },
      (resp) => {
        if (resp && resp.success) cb(resp.data);
      }
    );
  }

  function displayDropdown(products) {
    if (!products || !products.length) {
      $("#product_dropdown")
        .html('<div class="wcso-dropdown-item">No products found</div>')
        .show();
      return;
    }
    let html = "";
    products.forEach((p) => {
      html +=
        '<div class="wcso-dropdown-item" data-product=\'' +
        escapeHtml(JSON.stringify(p)) +
        "'>";
      html += "<strong>" + escapeHtml(p.name) + "</strong> ";
      html +=
        '<span class="wcso-badge wcso-badge-' +
        p.status +
        '">' +
        String(p.status).toUpperCase() +
        "</span>";
      html +=
        '<span style="float:right;">' + escapeHtml(p.price_html) + "</span>";
      html +=
        '<br><small style="color:#666;">ID: ' +
        p.id +
        (p.sku ? " | SKU: " + escapeHtml(p.sku) : "") +
        "</small>";
      html += "</div>";
    });
    $("#product_dropdown").html(html).show();
  }

  function hideDropdown() {
    $("#product_dropdown").hide();
  }
  function handleDropdownClose(e) {
    if (!$(e.target).closest("#product_search, #product_dropdown").length)
      hideDropdown();
  }
  function handleProductSelect() {
    const p = $(this).data("product");
    addProduct(p);
    $("#product_search").val("");
    hideDropdown();
  }

  // ========= Scanner =========
  function handleBarcodeKeydown(e) {
    if (e.which === 13) {
      e.preventDefault();
      const code = $(this).val().trim();
      if (code) {
        processBarcode(code);
        $(this).val("");
      }
      return false;
    }
  }
  let scanTimeout;
  function handleBarcodeInput() {
    clearTimeout(scanTimeout);
    const code = $(this).val().trim();
    scanTimeout = setTimeout(() => {
      if (code && code.length >= 3) {
        processBarcode(code);
        $("#barcode_input").val("").focus();
      }
    }, 500);
  }
  function processBarcode(code) {
    if (productsCache) {
      const res = searchInCache(code);
      handleBarcodeResults(res, code);
    } else {
      searchServer(code, (res) => handleBarcodeResults(res, code));
    }
  }
  function handleBarcodeResults(res, code) {
    if (res.length === 1) addProduct(res[0]);
    else if (res.length > 1) showModal(res, code);
    else showNotification("Product not found: " + code, "error");
    if (config.enableScanner) $("#barcode_input").focus();
  }

  // ========= Products management =========
  function addProduct(p) {
    const existing = selectedProducts.find((x) => x.id === p.id);
    if (existing) {
      existing.quantity++;
      showNotification(
        "Quantity increased: " + p.name + " (x" + existing.quantity + ")",
        "success"
      );
    } else {
      p.quantity = 1;
      selectedProducts.push(p);
      showNotification("Product added: " + p.name, "success");
    }
    updateProductsTable();
  }

  function handleQtyDecrease() {
    const id = $(this).data("id");
    const p = selectedProducts.find((x) => x.id === id);
    if (p && p.quantity > 1) {
      p.quantity--;
      updateProductsTable();
    }
  }
  function handleQtyIncrease() {
    const id = $(this).data("id");
    const p = selectedProducts.find((x) => x.id === id);
    if (p) {
      p.quantity++;
      updateProductsTable();
    }
  }
  function handleQtyChange() {
    const id = $(this).data("id");
    let q = parseInt($(this).val(), 10);
    if (isNaN(q) || q < 1) q = 1;
    const p = selectedProducts.find((x) => x.id === id);
    if (p) {
      p.quantity = q;
      updateProductsTable();
    }
  }
  function handleRemoveProduct(e) {
    e.preventDefault();
    const id = $(this).data("id");
    selectedProducts = selectedProducts.filter((x) => x.id !== id);
    updateProductsTable();
    showNotification("Product removed", "warning");
  }

  // ========= Table =========
  function updateProductsTable() {
    if (!selectedProducts.length) {
      $("#selected_products_table").html(
        '<div class="wcso-table-empty">No products selected. Search or scan to add products.</div>'
      );
      $("#selected_products_data").val("");
      $("#products-count").text("");
      return;
    }
    let totalQty = 0,
      totalPrice = 0;
    $("#products-count").text(
      selectedProducts.length +
        " items, " +
        selectedProducts.reduce((s, p) => s + p.quantity, 0) +
        " units"
    );

    let html =
      '<div class="wcso-table-wrapper"><table class="wcso-table"><thead><tr>';
    html +=
      "<th>#</th><th>Product</th><th>ID</th><th>SKU</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th>Status</th><th></th>";
    html += "</tr></thead><tbody>";

    selectedProducts.forEach((p, i) => {
      const itemTotal = (parseFloat(p.price) || 0) * p.quantity;
      totalQty += p.quantity;
      totalPrice += itemTotal;

      html += "<tr>";
      html += "<td>" + (i + 1) + "</td>";
      html += "<td><strong>" + escapeHtml(p.name) + "</strong></td>";
      html += "<td>" + p.id + "</td>";
      html += "<td>" + (p.sku ? escapeHtml(p.sku) : "—") + "</td>";
      html += "<td>" + p.price + "/-</td>";
      html += '<td><div class="wcso-qty-controls">';
      html +=
        '<button type="button" class="wcso-qty-btn wcso-qty-decrease" data-id="' +
        p.id +
        '">−</button>';
      html +=
        '<input type="number" class="wcso-qty-input" data-id="' +
        p.id +
        '" value="' +
        p.quantity +
        '" min="1">';
      html +=
        '<button type="button" class="wcso-qty-btn wcso-qty-increase" data-id="' +
        p.id +
        '">+</button>';
      html += "</div></td>";
      html += "<td><strong>" + itemTotal.toFixed(2) + "/-</strong></td>";
      html +=
        '<td><span class="wcso-badge wcso-badge-' +
        p.status +
        '">' +
        String(p.status).toUpperCase() +
        "</span></td>";
      html +=
        '<td><a href="#" class="wcso-remove-btn" data-id="' +
        p.id +
        '">×</a></td>';
      html += "</tr>";
    });

    html += "</tbody></table></div>";
    html += '<div class="wcso-summary">';
    html +=
      '<div class="wcso-summary-row"><span>Original Total:</span><span>' +
      totalPrice.toFixed(2) +
      "/-</span></div>";
    html +=
      '<div class="wcso-summary-row"><span>Discount (100%):</span><span>-' +
      totalPrice.toFixed(2) +
      "/-</span></div>";
    html +=
      '<div class="wcso-summary-row total"><span>Final Total:</span><span>0/-</span></div>';
    html +=
      '<div class="wcso-discount-badge">🎁 100% Sample Discount Applied</div>';
    html += "</div>";

    $("#selected_products_table").html(html);
    $("#selected_products_data").val(JSON.stringify(selectedProducts));
  }

  // ========= Modal =========
  function showModal(products, code) {
    $("#scanned_code").html(
      "<strong>Scanned Code:</strong> <code>" + escapeHtml(code) + "</code>"
    );
    let html = "";
    products.forEach((p) => {
      html +=
        '<div class="wcso-modal-item" data-product=\'' +
        escapeHtml(JSON.stringify(p)) +
        "'>";
      html += "<strong>" + escapeHtml(p.name) + "</strong> ";
      html +=
        '<span class="wcso-badge wcso-badge-' +
        p.status +
        '">' +
        String(p.status).toUpperCase() +
        "</span>";
      html +=
        "<br><small>ID: " +
        p.id +
        (p.sku ? " | SKU: " + escapeHtml(p.sku) : "") +
        " | " +
        escapeHtml(p.price_html) +
        "</small>";
      html += "</div>";
    });
    $("#modal_products").html(html);
    $("#product_modal").css("display", "flex");
  }
  function closeModal() {
    $("#product_modal").hide();
    if (config.enableScanner) $("#barcode_input").focus();
  }
  function handleModalProductSelect() {
    const p = $(this).data("product");
    addProduct(p);
    closeModal();
  }

  // ========= Submit =========
  function handleFormSubmit(e) {
    e.preventDefault();

    if (!selectedProducts.length)
      return alert("Please select at least one product");
    if (!$("#approved_by").val().trim()) {
      alert('Please fill in "Approved By" field');
      return;
    }

    $("#submit_order").prop("disabled", true);
    $("#order_loading").show();
    $("#order_message").html("");

    const stateValue =
      $("#shipping_state").is(":visible") &&
      !$("#shipping_state").prop("disabled")
        ? $("#shipping_state").val()
        : $("#shipping_state_text").length
        ? $("#shipping_state_text").val()
        : "";

    // Get selected shipping method data
    const $selectedMethod = $("#shipping_method option:selected");
    const methodData = $selectedMethod.data("methodData");

    const payload = {
      action: "wcso_create_order",
      nonce: config.nonces.order,
      billing_user_id: $("#billing_user_id").val(),

      shipping_first_name: $("#shipping_first_name").val(),
      shipping_last_name: $("#shipping_last_name").val(),
      shipping_company: $("#shipping_company").val(),
      shipping_country: $("#shipping_country").val(),
      shipping_address_1: $("#shipping_address_1").val(),
      shipping_address_2: $("#shipping_address_2").val(),
      shipping_city: $("#shipping_city").val(),
      shipping_state: stateValue,
      shipping_postcode: $("#shipping_postcode").val(),
      shipping_phone: $("#shipping_phone").val(),
      shipping_email: $("#shipping_email").val(),
      shipping_method_id: methodData ? methodData.method_id : "",
      shipping_method_title: methodData ? methodData.title : "",
      shipping_method_cost: methodData ? methodData.cost : "",
      shipping_method_instance_id: methodData ? methodData.instance_id : "",

      products: selectedProducts,
      approved_by: $("#approved_by").val(),
      order_note: $("#order_note").val(),
    };

    $.ajax({
      url: config.ajaxUrl,
      type: "POST",
      data: payload,
      success: function (resp) {
        $("#submit_order").prop("disabled", false);
        $("#order_loading").hide();

        if (resp && resp.success) {
          $("#order_message").html(
            '<div class="notice notice-success" style="padding:15px; margin-top:20px;">' +
              '<p style="margin:0;"><strong>✓ Success!</strong> ' +
              '<a href="' +
              resp.data.order_url +
              '" target="_blank" class="button button-primary" style="margin-left:10px;">View Order #' +
              resp.data.order_id +
              "</a></p></div>"
          );
          resetForm();
          $("html, body").animate(
            { scrollTop: $("#order_message").offset().top - 100 },
            500
          );
        } else {
          $("#order_message").html(
            '<div class="notice notice-error" style="padding:15px; margin-top:20px;"><p style="margin:0;"><strong>✗ Error:</strong> ' +
              (resp && resp.data ? resp.data : "Unknown error") +
              "</p></div>"
          );
        }
      },
      error: function () {
        $("#submit_order").prop("disabled", false);
        $("#order_loading").hide();
        $("#order_message").html(
          '<div class="notice notice-error" style="padding:15px; margin-top:20px;"><p style="margin:0;"><strong>Error:</strong> Connection failed. Please try again.</p></div>'
        );
      },
    });
  }

  function resetForm() {
    $("#wcso-order-form")[0].reset();
    selectedProducts = [];
    updateProductsTable();
    if (config.enableScanner) $("#barcode_input").focus();
    populateCountries();
  }

  // ========= Notifications & Utils =========
  function showNotification(message, type) {
    const colors = { success: "#46b450", error: "#dc3232", warning: "#ffb900" };
    $("<div>")
      .text(message)
      .css({
        position: "fixed",
        top: "80px",
        right: "20px",
        background: colors[type] || "#333",
        color: "#fff",
        padding: "12px 20px",
        borderRadius: "4px",
        zIndex: 99999,
        fontWeight: "600",
        boxShadow: "0 4px 6px rgba(0,0,0,0.2)",
      })
      .appendTo("body")
      .delay(3000)
      .fadeOut(300, function () {
        $(this).remove();
      });
  }

  function escapeHtml(txt) {
    if (!txt) return "";
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(txt).replace(/[&<>"']/g, (s) => map[s]);
  }

  // Boot
  init();
});
