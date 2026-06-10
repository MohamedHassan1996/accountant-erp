# InvoiceReportExportController Logic

## File

`app/Http/Controllers/Api/Private/Reports/InvoiceReportExportController.php`

## Purpose

هذا الكنترولر مسؤول عن تجهيز بيانات الفاتورة ثم تصديرها بأكثر من صيغة:

- `pdf`
- `csv` فعليًا يخرج ملف `xlsx`
- `xlsx`
- `xml`
- `zip` يحتوي أكثر من ملف `xml`

الكنترولر لا يعتمد على `ReportService` في منطق التصدير نفسه، بل يبني كل بيانات الفاتورة داخليًا.

---

## Entry Points

### `index(Request $request)`

هذه هي نقطة الدخول الأساسية لتصدير فاتورة واحدة.

تعتمد على `type` في الطلب:

- `pdf` -> `generateInvoicePdf()`
- `csv` -> `generateInvoiceExcel()`
- `xlsx` -> `generateSimpleXlsx()`
- `xml` -> `generateInvoiceXml()`

في حالة `xml` يوجد شرط إضافي:

- يجب أن يكون لدى العميل:
  - `CAB`
  - `ABI`
  - `bankName`

وإلا يرجع:

```json
{
  "message": "Questo cliente non ha CAB, ABI e nome banca associati"
}
```

### `exportMultipleXmlZip(Request $request)`

هذه نقطة دخول لتصدير عدة فواتير XML مرة واحدة داخل ملف ZIP.

الخطوات:

1. يتحقق من وجود `invoiceIds`
2. يمر على كل `invoiceId`
3. يبني بيانات الفاتورة عبر `getInvoiceDataById()`
4. يتحقق من بيانات البنك المطلوبة للـ XML
5. يبني محتوى XML لكل فاتورة عبر `buildInvoiceXmlPayload()`
6. يجمعهم داخل ملف ZIP في:
   - `storage/app/public/exportedInvoices`
7. يرجع رابط الملف

---

## Data Building

### `getInvoiceData(Request $request)`

هذه مجرد wrapper ترسل أول عنصر من:

`$request->invoiceIds[0]`

إلى:

`getInvoiceDataById()`

### `getInvoiceDataById(int $invoiceId)`

هذه أهم دالة في الكنترولر، لأنها تبني كل البيانات الموحدة التي تستخدمها كل صيغ التصدير.

#### 1. تحميل الفاتورة

- يجلب الفاتورة عبر `Invoice::findOrFail($invoiceId)`

#### 2. تحميل عناصر الفاتورة

يقرأ من جدول `invoice_details` مباشرة ويجلب:

- `price`
- `price_after_discount`
- `invoiceable_id`
- `invoiceable_type`
- `description`
- `quantity`
- `unit_price`

#### 3. تفسير نوع كل سطر

كل سطر في `invoice_details` هو `morph` ويمكن أن يشير إلى:

- `Task`
- `ClientPayInstallment`
- `ClientPayInstallmentSubData`

وبناءً عليه يتم تحميل الكيان الحقيقي:

- `Task::with('serviceCategory')`
- `ClientPayInstallment::with('parameterValue')`
- `ClientPayInstallmentSubData::with('parameterValue')`

#### 4. وصف السطر

الوصف يتحدد بهذا الشكل:

- لو السطر من `Task`:
  - يستخدم اسم `serviceCategory`
- لو من `ClientPayInstallment` أو `SubData`:
  - يستخدم `parameterValue->description`
  - وإذا لم يوجد، يستخدم `invoice_details.description`
- إذا كان `invoice_details.description` نفسه غير فارغ فهو يتغلب على أي وصف آخر

#### 5. تاريخ بداية الفاتورة

الافتراضي:

- `created_at` للفاتورة

لكن إذا كان السطر من `ClientPayInstallment`:

- يتم أخذ التاريخ من `start_at` لذلك السطر

#### 6. Service Code

كل سطر يحاول أخذ `serviceCode` لاستخدامه لاحقًا خصوصًا في XML:

- `Task` -> `serviceCategory->code`
- `ClientPayInstallment` -> `parameterValue->code`
- `ClientPayInstallmentSubData` -> `parameterValue->code`
- الافتراضي: `..`

#### 7. الكمية وسعر الوحدة

- إذا `quantity > 0` يستخدمها
- وإلا يجعل الكمية `1`
- إذا `unit_price > 0` يستخدمه
- وإلا يعتمد على `price`

ثم يبني لكل سطر:

- `description`
- `price`
- `priceAfterDiscount`
- `additionalTaxPercentage`
- `serviceCode`
- `quantity`
- `unitPrice`
- `total`

#### 8. الضريبة الأساسية

كل سطر عادي يبدأ بنسبة:

- `22%`

#### 9. Extra Price من الـ Service Category

إذا كان السطر من `Task` و `serviceCategory->extra_is_pricable = true`

يضيف سطرًا إضافيًا إلى الفاتورة:

- الوصف من `extra_price_description`
- السعر من `extra_price`
- الضريبة `0%`
- الكود من `extra_code`

هذا السطر يزيد:

- `invoiceTotal`

لكنه لا يدخل ضمن أساس الـ IVA 22%

#### 10. Total Tax الخاص بالعميل

إذا كان العميل لديه:

- `total_tax > 0`

فيتم إنشاء سطر إضافي يمثل هذه الزيادة.

حسابه يعتمد على:

- `invoiceTaxableTotal * (total_tax / 100)`

وإذا كان لدى العميل:

- `limit_decreto > 0`

فيتم عمل cap على المبلغ.

السطر الناتج:

- ضريبة 22%
- `serviceCode = 00000001`

ثم يتم تحديث:

- `invoiceTotal`
- `invoiceTaxableTotal`

#### 11. عنوان العميل

يأخذ أول عنوان من:

`ClientAddress::where('client_id', $client->id)->first()`

ويستخدم:

- نسخة string مبسطة `clientAddress`
- ونسخة كـ object في `clientAddressData`

#### 12. بنك العميل

يحاول أولًا أخذ الحساب البنكي الأساسي:

- `is_main = 1`

وإذا لم يجده:

- يأخذ أول حساب بنكي متاح

ثم يبني:

- `iban`
- `abi`
- `cab`
- `bankName`

#### 13. Discount على مستوى الفاتورة

إذا الفاتورة فيها:

- `discount_amount > 0`

فيتم حساب الخصم:

- إذا `discount_type == 0` -> نسبة مئوية
- غير ذلك -> مبلغ ثابت

ويضاف كسطر:

- الوصف: `sconto`
- ضريبة: `0%`

ثم يخصم من:

- `invoiceTotal`
- `invoiceTotalToCalcTax`

#### 14. Payment Method

يستخدم:

`ParameterValue::find($invoice->payment_type_id)`

ثم يخرج:

- `paymentMethod` = `code`
- `paymentMethodName` = `parameter_value`

هذا مهم جدًا للـ XML:

- مثل `MP05`
- أو `MP12`

#### 15. حساب IVA النهائي

في النهاية:

- `invoiceTotalTax = invoiceTaxableTotal * 0.22`

ويرجع أيضًا:

- `invoiceTotal`
- `invoiceTaxableTotal`
- `invoiceTotalWithTax = invoiceTotal + invoiceTotalTax`

#### 16. حساب البنك الافتراضي للمكتب

إذا الفاتورة لا تحتوي `bank_account_id`:

- يستخدم أول `ParameterValue` حيث:
  - `parameter_order = 7`
  - `is_default = 1`

ثم يخرج:

- `iban` من `parameter_value`
- `abi` من `description2`
- `cab` من `description3`
- `bankName` من `description`

---

## PDF Export

### `generateInvoicePdf(array $data)`

قبل إنشاء PDF:

يحاول معرفة هل يجب إضافة `imposta di bollo` أم لا.

القاعدة:

- يجمع كل الأسطر التي `additionalTaxPercentage = 0`
- إذا كان مجموعها أكبر من `77.47`
  - يضيف stamp منطقيًا بقيمة `2.00`

ثم:

- يحمل view اسمها `invoice_pdf_report`
- ينشئ ملف PDF
- يخزنه في:
  - `storage/app/public/exportedInvoices`
- يرجع `path`

---

## XLSX Export

### `generateSimpleXlsx(array $data)`

ينشئ ملف `xlsx` بسيط جدًا بثلاثة أعمدة:

- `Cliente`
- `Descrizione`
- `Totale`

لكل سطر:

- إذا كانت الضريبة > 0
  - يحسب total شامل الضريبة
- إذا كانت 0
  - يترك total = السعر فقط

ثم يحفظ الملف في `public/exportedInvoices` ويرجع الرابط.

### `generateInvoiceExcel($data)`

رغم الاسم `Excel` وكون `type=csv` يمر من هنا، الدالة فعليًا تنشئ أيضًا ملف `xlsx`.

الأعمدة:

- `Cliente`
- `Descrizione`
- `Prezzo unitario`
- `Quantitestazione`
- الإجمالي
- تاريخ الفاتورة

ملاحظة مهمة:

هذه الدالة تستخدم `quantita` بدل `quantity` داخل السطور، بينما تجهيز البيانات في `getInvoiceDataById()` يبني المفتاح باسم `quantity`.

بالتالي هذه الدالة فيها عدم اتساق محتمل:

- قد ترجع الكمية دائمًا `1`

---

## XML Export

### `generateInvoiceXml(array $data)`

هذه فقط wrapper تستدعي:

- `buildInvoiceXmlPayload($data)`

ثم ترجع:

- `name`
- `content`

بعد تحويل encoding إلى UTF-8 للرد JSON

### `buildInvoiceXmlPayload(array $data)`

هذه هي دالة بناء XML الفعلية.

#### 1. Sanitization

تحتوي على helper لإزالة accents وبعض الأحرف الخاصة من النصوص.

ثم helper باسم `safe()` لعمل:

- trim
- sanitize
- `htmlspecialchars(..., ENT_XML1)`

#### 2. Parsing Dates

الدالة `parseDate()` تحاول دعم:

- `d/m/Y`
- أي صيغة أخرى يستطيع `Carbon::parse()` قراءتها

وفي حالة الفشل:

- تستخدم `now()`

#### 3. Passepartout Logic

يوجد flag:

`$usePassepartout`

ويعتمد على:

- `client.sdi_code`
- وألا يكون `0000000`

لكن لاحظ أن بقية الكود يستخدم غالبًا `client.sdi`
وليس `sdi_code`

فهنا يوجد احتمال mismatch في أسماء الحقول.

#### 4. Stamp Duty

نفس منطق PDF:

- إذا مجموع الأسطر ذات IVA = 0 أكبر من `77.47`
  - يضيف bollo بقيمة `2.00`

#### 5. Root XML

يبدأ بـ:

- `FatturaElettronica`
- `versione = FPR12`

ثم في النهاية يحولها إلى root namespace prefixed باسم:

- `p:FatturaElettronica`

#### 6. Header

يبني:

- `DatiTrasmissione`
- `CedentePrestatore`
- `CessionarioCommittente`
- `TerzoIntermediarioOSoggettoEmittente`

#### 7. ProgressivoInvio

إذا الفاتورة لديها أصلًا:

- `invoice_xml_number`

يستخدمه كما هو.

إذا لا:

- يدخل transaction
- يأخذ `ParameterValue` حيث `parameter_order = 13`
- يعمل `lockForUpdate`
- يقرأ الرقم الحالي مثل `1/60`
- يزيد الجزء الثاني
- يحفظ القيمة الجديدة
- ثم يحدث الفاتورة بحقل `invoice_xml_number`

هذا يمنع تضارب الأرقام في التوازي.

#### 8. CedentePrestatore

البيانات هنا hardcoded تقريبًا للشركة المصدرة:

- `ELABORAZIONI SRL`
- `P.IVA = 00987920196`
- بيانات العنوان وREA والاتصال ثابتة

#### 9. CessionarioCommittente

يُبنى من بيانات العميل:

- `iva`
- `cf`
- `ragione_sociale`
- عنوان العميل

#### 10. Body / DatiGeneraliDocumento

يضيف:

- `TipoDocumento = TD01`
- `Divisa = EUR`
- `Data`
- `Numero`
- `ImportoTotaleDocumento`

الرقم المستخدم في `Numero` هو الجزء الثاني فقط من `invoice_xml_number`.

#### 11. Causale

يأخذ أول item موجب السعر وله وصف، ويضعه كـ `Causale`.

#### 12. DatiBeniServizi

يبني سطر XML لكل item:

- إذا `priceAfterDiscount <= 0`
  - يتجاهله
- إذا `AliquotaIVA != 0`
  - يضيف `CodiceArticolo`
- إذا `AliquotaIVA == 0`
  - يضيف `Natura`

لكل سطر:

- `NumeroLinea`
- `Descrizione`
- `Quantita`
- `UnitaMisura = NR`
- `PrezzoUnitario`
- `PrezzoTotale`
- `AliquotaIVA`

#### 13. Stamp Line

إذا stamp مطلوب:

- يضيف سطرًا إضافيًا باسم:
  - `Imposta di bollo`
- `AliquotaIVA = 0`
- `Natura = N1`

#### 14. DatiRiepilogo

يبني قسمين عادة:

- Summary لـ `22% IVA`
- Summary لـ `0%` مع `Natura = N1` إذا وُجدت أسطر معفاة

القيمة المعفاة تجمع:

- `extraTotal`
- + stamp إذا كان موجودًا

#### 15. Payment Section

يبني:

- `DatiPagamento`
- `CondizioniPagamento = TP02`
- `DettaglioPagamento`

ثم:

- `ModalitaPagamento` من `paymentMethod`
- `DataScadenzaPagamento`
- `ImportoPagamento`

حسب نوع الدفع:

- إذا `MP05`
  - يضع `IstitutoFinanziario`
  - و `IBAN`
- إذا `MP12`
  - يضع `IstitutoFinanziario`
  - و `ABI`
  - و `CAB`

#### 16. Save XML

في النهاية:

- اسم الملف يكون:
  - `00987920196_000xx.xml`
- يحفظ في:
  - `storage/app/exportedInvoices`

ثم يرجع:

- `name`
- `content`

---

## Important Notes

### 1. `csv` لا يخرج CSV فعليًا

المسار `type=csv` يستدعي `generateInvoiceExcel()` التي تنشئ `xlsx`.

### 2. يوجد عدم اتساق بين `quantity` و `quantita`

في تجهيز البيانات المفتاح هو:

- `quantity`

لكن في `generateInvoiceExcel()` يتم استخدام:

- `quantita`

هذا قد يؤدي إلى نتائج غير دقيقة في ملف التصدير.

### 3. `clientAddressData->toArray()`

داخل `getInvoiceDataById()` يوجد:

```php
'clientAddressData' => $clientAddressData->toArray(),
```

إذا لم يوجد عنوان للعميل، هذا قد يسبب error لأن `first()` قد يرجع `null`.

### 4. منطق XML يعتمد على `invoice->payment_type_id`

يعني إذا كانت الفاتورة نفسها لا تملك `payment_type_id` صحيح، قد يتأثر:

- `paymentMethod`
- `paymentMethodName`
- تفاصيل `DatiPagamento`

### 5. بيانات المصدر hardcoded

بيانات الشركة المصدرة داخل XML ثابتة داخل الكود، وليست ديناميكية من إعدادات النظام.

---

## Summary

الكنترولر يعمل على مرحلتين أساسيتين:

1. **تجهيز بيانات الفاتورة**
   - سطور الفاتورة
   - الإضافات
   - الخصومات
   - الضريبة
   - بيانات العميل
   - بيانات البنك
   - طريقة الدفع

2. **تحويل نفس البيانات إلى أكثر من صيغة**
   - PDF
   - XLSX
   - XML
   - ZIP متعدد XML

أهم دالة في فهم المنطق كله هي:

- `getInvoiceDataById()`

وأهم دالة في تصدير XML هي:

- `buildInvoiceXmlPayload()`
