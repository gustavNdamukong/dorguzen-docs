# PDF Generation

Dorguzen does not ship a built-in PDF library. PDF generation is an opt-in capability — install a Composer package when your application needs it. This keeps the framework lean for applications that never generate PDFs.

---

## 2.1 Choosing a PDF Library

There are several well-maintained PHP PDF libraries. Here is an honest comparison so you can make the right choice for your use case:

| Library | Approach | Strengths and best use cases |
|---------|----------|------------------------------|
| Dompdf | HTML → PDF | Easiest to use. You write HTML/CSS and it renders to PDF. Perfect for receipts, invoices, reports where you already have a design in HTML. |
| mPDF | HTML → PDF | Same idea as Dompdf. Better Unicode and multilingual/RTL text support. Good if your PDFs contain non-Latin characters (Arabic, Chinese, etc.). |
| TCPDF | Programmatic | Low-level, no HTML rendering. You build the PDF by calling methods (drawLine, addCell, etc.). More code but total control. Good for complex layouts like certificates or formatted tables. |
| Browsershot (Puppeteer) | Headless browser → PDF | Uses a real browser (Puppeteer/Chrome) to render HTML. Pixel-perfect output, supports modern CSS (flexbox, grid, custom fonts). Requires Node.js on the server. Overkill for most web apps but the best option for print-quality output. |

---

## 2.2 Why We Recommend Dompdf

For most Dorguzen applications — receipts, order confirmations, reports, invoices, subscription summaries — Dompdf is the right starting point:

- Pure PHP, no external binaries or Node.js required.
- You design PDF content as an HTML template, exactly like you already design email templates. The same HTML skills apply.
- Actively maintained (composer package: dompdf/dompdf).
- Handles CSS styling, embedded images, page headers/footers, and most common layouts out of the box.
- Easy to swap to mPDF later if you hit Unicode limitations — both share the HTML-in, PDF-out model.

If your application has significant multilingual content (especially RTL languages), start with mPDF instead. If you need pixel-perfect rendering of complex modern CSS, consider Browsershot.

---

## 2.3 Installing Dompdf

```php
composer require dompdf/dompdf
```

That is all. No server configuration needed. Dompdf works on any PHP 7.1+ environment including MAMP, shared hosting, and cloud servers.

---

## 2.4 Code Examples

### A. Simple HTML string to PDF (download)

The simplest possible usage — convert an HTML string to a PDF and stream it to the browser as a file download.

```php
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml('
    <h1>Order Receipt</h1>
    <p>Thank you for your order.</p>
    <p><strong>Total: $49.99</strong></p>
');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('receipt.pdf', ['Attachment' => true]);
exit;
```

`stream()` sends the PDF directly to the browser. `['Attachment' => true]` triggers a file download. `['Attachment' => false]` displays it inline (see example E).

Always call `exit` after `stream()` to prevent any further PHP output from corrupting the PDF binary.

---

### B. Saving a PDF to disk

Useful when you want to store a generated receipt or report on the server (e.g. in `storage/pdfs/`) to attach to an email or serve later.

```php
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml('<h1>Invoice #1042</h1><p>Amount due: $120.00</p>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfContent = $dompdf->output();  // returns the PDF as a string
$savePath   = '/absolute/path/to/storage/pdfs/invoice_1042.pdf';
file_put_contents($savePath, $pdfContent);

// Now you can attach $savePath to an email, or redirect to a download route.
```

---

### C. Rendering a PHP view file as a PDF

The most practical pattern for Dorguzen applications: design your receipt or report as a PHP view file (just like any other view), capture its output, and pass it to Dompdf. This keeps your PDF layout in a maintainable template rather than hardcoded strings.

Step 1 — Create the view file at `views/pdfs/receipt.php`:

```php
<!DOCTYPE html>
<html>
<head>
    <style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 13px; }
        h1    { color: #333; border-bottom: 2px solid #fd7e14; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th    { background: #fd7e14; color: #fff; padding: 8px; text-align: left; }
        td    { padding: 8px; border-bottom: 1px solid #eee; }
        .total { font-weight: bold; font-size: 15px; }
    </style>
</head>
<body>
    <h1>Order Receipt</h1>
    <p>Thank you, <?= htmlspecialchars($name) ?>. Here is your order summary:</p>
    <table>
        <tr><th>Item</th><th>Qty</th><th>Price</th></tr>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= (int) $item['qty'] ?></td>
            <td>$<?= number_format($item['price'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="total">Total: $<?= number_format($total, 2) ?></p>
    <p style="color:#999; font-size:11px;">Generated on <?= date('d M Y') ?></p>
</body>
</html>
```

Step 2 — In your controller or service, render the view to a string and pass it to Dompdf:

```php
use Dompdf\Dompdf;

// Capture the view output into a string (ob = output buffer)
$name  = $order['customer_name'];
$items = $order['items'];
$total = $order['total'];

ob_start();
include BASE_PATH . '/views/pdfs/receipt.php';
$html = ob_get_clean();

// Generate and stream the PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('receipt.pdf', ['Attachment' => true]);
exit;
```

Note: Dompdf renders PDF in a browser-like environment, not a web server context. Use absolute filesystem paths for any embedded images (not URLs), and use DejaVu Sans (or another font known to Dompdf) for reliable character rendering.

For embedded images, reference them with an absolute path:

```php
<img src="/absolute/path/to/assets/images/logo.png">
```

---

### D. Paper size and orientation

`setPaper()` takes a size name and orientation:

```php
$dompdf->setPaper('A4', 'portrait');   // standard document
$dompdf->setPaper('A4', 'landscape');  // wide table or chart
$dompdf->setPaper('letter', 'portrait'); // US letter size
```

Common size names: `'A4'`, `'A3'`, `'letter'`, `'legal'`, `'folio'`. Or pass custom dimensions in points (1 inch = 72 points):

```php
$dompdf->setPaper([0, 0, 595, 842]);   // A4 in points (width x height)
```

---

### E. Inline display in the browser (no download prompt)

To open the PDF directly in the browser's built-in PDF viewer rather than triggering a download, set `'Attachment'` to false:

```php
$dompdf->render();
$dompdf->stream('receipt.pdf', ['Attachment' => false]);
exit;
```

This is useful for a "preview receipt" page. Whether the browser displays it inline or still prompts a download depends on the user's browser settings, but modern browsers (Chrome, Firefox, Safari) all display PDFs inline by default.

---

Return to [Introduction](/dorguzen-docs/docs/introduction) or use the sidebar to navigate.
