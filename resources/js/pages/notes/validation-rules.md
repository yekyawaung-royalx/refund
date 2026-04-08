# CSV Import Validation Rules
Date: 2026-03-22  
Author: Ye Kyaw Aung  

---
## No Refund File
### Validation
- File format (.csv)
- CSV column count `(33 cols)`
- Waybill duplicate in CSV file
- Waybill duplicate in database
- Weight must not be `0` or `empty`

### Import Skip
- Invalid date format (e.g. 12/4/2026)
- Escape special characters (/!%#*@)
  - `receiver_name`
  - `receiver_address`
  - `recipient_name`
  - `recipient_phone`

---
## Refund File

### Validation
- File format (.csv)
- CSV column count `(7 cols)`
- Waybill duplicate in CSV file

### Import Skip
- Invalid date format (e.g. `12/4/2026`)
- Escape special characters (`/*%$#@!`)
- Waybill not found (saved in failed logs)
- Waybill already refunded (saved in failed logs)
- Waybill amount not equal (saved in failed logs)

---

*End of Notes*