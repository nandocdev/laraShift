> ## Documentation Index
> Fetch the complete documentation index at: https://docs.dlocal.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Set up guide

Discover everything you need to know about configuring Smart Fields.

# Introduction

This guide will walk you through the creation of a payment form using dLocal's all-in-one `card` Smart Field (also referred to as '`card`Field'). The `card` Field simplifies the form and minimizes the number of fields required by inserting a single, flexible input field that securely collects all necessary card details.

You can make use of Smart Fields (our pre-built UI components) to create a payment form that securely collects your customer’s card information without you needing to handle sensitive card data. The card details are then converted to a representative token that you can safely send to your servers.

Below is a live demo of the `card` Smart Field. Try changing the width of the browser to see how the Field adapts. You can make changes to the code on CodePen:

[block:embed]
{
  "html": "<iframe height='350' scrolling='no' src='https://codepen.io/martindlocal/embed/MBeJdN' frameborder='no' allowtransparency='true' allowfullscreen='true' style='width: 100%;'></iframe>",
  "url": "https://codepen.io/martindlocal/pen/MBeJdN",
  "title": "Fields-simple-example",
  "favicon": "https://cpwebassets.codepen.io/assets/favicon/favicon-aec34940fbc1a6e787974dcd360f2c6b63348d4b1f4e06c77743096d55480f33.ico",
  "image": "https://shots.codepen.io/martindlocal/pen/MBeJdN-512.jpg?version=1635546664",
  "provider": "codepen.io",
  "href": "https://codepen.io/martindlocal/pen/MBeJdN"
}
[/block]

Creating a custom payment form with Smart Fields requires five steps:

1. Set up dLocal Smart Fields.
2. Create your payment form.
3. Create installments plan (optional)
4. Create a token to securely transmit card information.
5. Submit the token and the rest of your form to your server.

[block:tutorial-tile]
{
  "backgroundColor": "#018FF4",
  "emoji": "💻",
  "id": "61f97e8e125dd402f6a6f4d2",
  "link": "https://docs-dlocal.readme.io/v2.1/recipes/smart-fields-javascript-guide",
  "slug": "smart-fields-javascript-guide",
  "title": "Smart Fields JavaScript Guide"
}
[/block]

***

# Before you start: HTTPS requirements

All submissions of payment info using Smart Fields are made via a secure HTTPS connection. However, to protect yourself from certain forms of man-in-the-middle attacks, and to prevent your customers from seeing [Mixed Content](https://developers.google.com/web/fundamentals/security/prevent-mixed-content/what-is-mixed-content) warnings in modern browsers, you must serve the page containing the payment form over HTTPS as well.

***

# Using Smart Fields

## Step 1: Set up Smart Fields

Smart Fields is available as part of dLocal.js. Include this script on your pages to get started —it should always be loaded directly from <https://js.dlocal.com>. For testing purposes, you can use <https://js-sandbox.dlocal.com>.

<br />

> For details on using dLocal.js or implementing Subresource Integrity (SRI), refer to our [dlocal.js Reference](https://docs.dlocal.com/reference/dlocaljs-reference) page.

<br />

```markup
<script src="https://js.dlocal.com/"></script>
```

Next, create an instance of Smart Fields (referred to as just `fields()`):

```javascript
var dlocal = dlocal('your_API_key');
var fields = dlocal.fields({
            locale: 'en',
            country: 'BR'
        });
```

<br />

> ℹ️ Keep your credentials handly
>
> To initialize the dLocal helper you will need your API Credential. <br>Configure your integration using the [production keys](https://dashboard.dlocal.com/settings/integration).

<br />

***

## Step 2: Create your payment form

To securely collect card details from your customers, Smart Fields creates UI components for you that are hosted by dLocal. They are then placed into your payment form, rather than you creating them directly.

To determine where to insert these components, create empty DOM elements (containers) with unique IDs within your payment form. We recommend placing your container within a `<label>` or next to a `<label>` with a `for` attribute that matches the unique `id` of the Smart Field container. By doing so, the Field automatically gains focus when the customer clicks on the corresponding label.

For example:

```html
<form action="/charge" method="post" id="payment-form">
    <div class="form-row">
        <label for="card-field">
        Credit or Debit card
        </label>
        <div id="card-field">
            <!-- A dLocal Smart Field will be inserted here. -->
        </div>

        <!-- Used to display Smart Field errors. -->
        <div id="card-errors" role="alert"></div>
    </div>
    <div class="form-rowd">
        <label>Cardholder name</label>
        <input id="card-holder" type="text" name="card-holder" placeholder="John Doe" />
    </div>
    <button>Pay</button>
</form>
```

When the form above has loaded, create an instance of a Field and mount it to the Field container created above:

```javascript
// Custom styling can be passed to options when creating a Smart Field.
var style = {
  base: {
    // Add your base input styles here. For example:
    fontSize: '16px',
    color: "#32325d",
  }
};

// Create an instance of the card Field.
var card = fields.create('card', {style: style});

// Add an instance of the card Field into the `card-field` <div>.
card.mount(document.getElementById('card-field'));
```

Smart Fields validate user input as it is typed. To help your customers catch mistakes, you should listen to `change` events on the `card` Field and display any errors:

```javascript
card.addEventListener('change', function(event) {
  var displayError = document.getElementById('card-errors');
  if (event.error) {
    displayError.textContent = event.error.message;
  } else {
    displayError.textContent = '';
  }
});
```

> 📘 **Set legal policies**
>
> For Smart Fields integration, you need to link to dLocal Privacy policy when the customers are completing their checkout to get an agreement to legal terms. You can use the following example to add it:
>
> *\[Merchant] is powered by dLocal, which has been appointed by \[Merchant] to provide payment services on its behalf, including the collection of the data necessary to facilitate and remit your payments. As such, you are now providing your personal data to dLocal. For more information, please visit dLocal’s [Privacy Hub](https://www.dlocal.com/legal/privacy-hub/)*

***

## Step 3: Create installments plan (Optional)

You can specify an installment plan, to guarantee the surcharge per installment that will be charged.

```html
<!-- Add to your form -->
<div class="form-row">
    <label>Fees to pay</label>
    <div class="select-wrapper">
    <span>▼</span>
    <select id="installments" disabled>
        <option value="">Enter the card number first</option>
    </select>
    </div>
</div>
```

> ℹ️ Note
>
> It is highly recommended that you include the `createInstallmentsPlan` on the `**brand**` event. This is because the installment plan *only* depends on the amount and card brand.

```javascript
let actualBrand = null;
card.on('brand', function (event) {
    document.getElementById('card-errors').innerHTML = "";
    if (event.brand) {
        //when card brand changes
        actualBrand = cardStatus.brand;
        //totalAmount & currency of the purchase
        dlocal.createInstallmentsPlan(card, totalAmount, currency)
        .then((result) => {
            var installmentsSelect = document.getElementById('installments');
            buildInstallments(installmentsSelect, result.installments);
        }).catch((result) => {
            console.error(result);
        });
    }
});

function buildInstallments(installmentsInput, installmentsPlan) {
    const installmentsOptions = installmentsPlan.installments.reduce(function (options, plan) {
            options += "<option value=" + plan.id + ">" + plan.installments + " of " + currency + " " + plan.installment_amount + " (Total : " + currency + " " + plan.total_amount + ")</option>";
            return options;
    }, "");
    installmentsInput.disabled = false;
    installmentsInput.innerHTML = installmentsOptions;
}
```

***

## Step 4: Create a token to securely transmit card information

The payment details collected using Smart Fields can then be converted into a token. Create an event handler that handles the submit event on the form. The handler sends the sensitive information to dLocal for tokenization and prevents the form’s submission (the form is submitted by JavaScript in the next step).

```javascript
// Create a token or display an error when the form is submitted.
var form = document.getElementById('payment-form');
form.addEventListener('submit', function(event) {
  event.preventDefault();
  var cardHolderName = document.getElementById('card-holder').value;
  dlocal.createToken(card, {
    name: cardHolderName
  }).then(function(result) {
      // Send the token to your server.
      dlocalTokenHandler(result.token);
  }).catch((result) => {
    if (result.error) {
      // Inform the customer that there was an error.
      var errorField = document.getElementById('card-errors');
      errorField.textContent = result.error.message;
    }
  });
});
```

`dlocal.createToken` returns a `Promise` which resolves with a `result` object. This object has `result.token` the token that was successfully created.

***

## Step 5: Submit the token and the rest of your form to your server

The last step is to submit the token, along with any additional information that has been collected, to your server.

```javascript
function dlocalTokenHandler(token) {
  // Insert the token ID into the form so it gets submitted to the server
  var form = document.getElementById('payment-form');
  var tokenInput = document.createElement('input');
  tokenInput.setAttribute('type', 'hidden');
  tokenInput.setAttribute('name', 'dlocalToken');
  tokenInput.setAttribute('value', token);
  form.appendChild(tokenInput);

  // Submit the form
  form.submit();
}
```

> ℹ️ Note
>
> **Tokens created with this method expire after 10 minutes, or after one operation with that token is made (eg: Payment or Save Card)**. If you want to save the card to make other payments later, you need to **save the card**. [Learn more about saving cards](https://docs.dlocal.com/docs/saving-cards).