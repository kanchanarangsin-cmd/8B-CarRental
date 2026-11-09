# design.md

**ระบบ:** ระบบเช่ารถยนต์ (Car Rental System)  
**รหัสกลุ่ม:** 8B  
**สถานะ:** ฉบับสุดท้ายหลังผ่าน Exit Criteria  
**Mood Keywords:** อบอุ่น (Warm) · สงบ (Calm) · น่าเชื่อถือ (Trustworthy)

> Brand Constraint: โลโก้และสีโลโก้เดิมเป็น Brand Asset ที่ห้ามแก้ไข ทุกหน้าต้องใช้โลโก้เดิมเท่านั้น

## Section 1 — Color Tokens

| Token | ค่า | ใช้ที่ไหน | ความหมาย / Rule |
|---|---|---|---|
| `color.nav` | `#332A46` | Navigation, พื้นหลัง Overlay แบบโปร่งใส | ม่วงเข้มสื่อความมั่นคงและน่าเชื่อถือ |
| `color.primary` | `#6B55B4` | ปุ่มหลัก, Active State, Summary, Chart | ม่วงพาสเทลเชื่อมกับโลโก้เดิมและเป็นจุดนำสายตา **(R-01)** |
| `color.background` | `#F6F2FC` | พื้นหลังหน้า | ม่วงขาวโทนอุ่น ลดความแสบตา |
| `color.surface` | `#FFFDFF` | Table, Card, Menu, Modal | พื้นผิวอุ่น แยกเนื้อหาออกจากพื้นหลัง |
| `color.text.primary` | `#342D40` | Heading, Body, ข้อมูลหลัก | อ่านชัดโดยไม่แข็งเท่าสีดำ |
| `color.text.secondary` | `#6F667A` | คำอธิบาย, Placeholder, Result Count | ลดลำดับความสำคัญของข้อมูลรอง |
| `color.border` | `#DED5EC` | Border, Divider, Table Line | แบ่งพื้นที่อย่างนุ่มนวล |
| `color.surface.pastel` | `#EEE9FA` | ปุ่มรอง, Header Table, Hover, Focus Ring | สถานะรองที่ยังอยู่ในโทนเดียวกัน |
| `color.badge.background` | `#EFE7F8` | Status Badge | พื้นสถานะที่ไม่แย่งจุดสนใจ |
| `color.badge.text` | `#67468F` | ข้อความ Status Badge | อ่านชัดบนพื้น Badge |
| `color.danger` | `#A53D3D` | ปุ่มและข้อความลบ, Error State | ระบุการกระทำที่มีผลย้อนกลับไม่ได้ **(R-05)** |
| `color.overlay` | `rgba(51, 42, 70, 0.48)` | Modal Backdrop | สร้างจาก `color.nav` ที่ Opacity 48% เพื่อรักษาบริบทขณะยืนยัน **(R-05)** |

### Contrast Check — WCAG AA

| ข้อความ | พื้นหลัง | Contrast Ratio | ผล |
|---|---|---:|---|
| `#FFFDFF` | `#332A46` | 13.30:1 | ผ่าน AA |
| `#FFFDFF` | `#6B55B4` | 5.79:1 | ผ่าน AA |
| `#342D40` | `#F6F2FC` | 11.92:1 | ผ่าน AA |
| `#342D40` | `#FFFDFF` | 13.00:1 | ผ่าน AA |
| `#6F667A` | `#FFFDFF` | 5.38:1 | ผ่าน AA |
| `#67468F` | `#EFE7F8` | 6.13:1 | ผ่าน AA |
| `#A53D3D` | `#FFFDFF` | 6.22:1 | ผ่าน AA |
| `#342D40` | `#EEE9FA` | 11.08:1 | ผ่าน AA |

ทุกคู่ที่ใช้กับข้อความปกติมีค่าไม่ต่ำกว่า 4.5:1

## Section 2 — Typography

**Font Stack:** `Leelawadee UI`, `Noto Sans Thai`, `Tahoma`, `Arial`, `sans-serif`

| Token | ขนาด / Line-height | Weight | ใช้กับ |
|---|---|---|---|
| `type.display` | `44px / 56px` | 800 | ชื่อหน้าหลัก |
| `type.metric` | `30px / 40px` | 900 | ตัวเลข Summary |
| `type.heading` | `24px / 32px` | 800 | หัวข้อ Section |
| `type.subheading` | `22px / 32px` | 800 | หัวข้อ Card / Modal |
| `type.component-title` | `20px / 32px` | 700 | หัวข้อกลุ่ม Control |
| `type.lead` | `18px / 32px` | 400 | คำอธิบายสำคัญ |
| `type.body` | `16px / 24px` | 400–800 | Body, Table, Button, Input |
| `type.caption` | `14px / 24px` | 700–900 | Label, Badge, Result Count |
| `type.micro` | `12px / 16px` | 400–700 | แกนกราฟและหมายเหตุสั้น |

**Visual Hierarchy Rule:** Display > Metric > Heading > Subheading > Component Title > Lead > Body > Caption > Micro  
Heading และข้อมูลหลักใช้ `color.text.primary`; คำอธิบายใช้ `color.text.secondary`; ข้อความลบใช้ `color.danger`

## Section 3 — Spacing & Layout

### 8px Spacing Scale

| Token | ค่า | ตัวอย่างการใช้ |
|---|---:|---|
| `space-1` | 8px | ช่องว่าง Label กับ Control |
| `space-2` | 16px | Gap ภายในกลุ่ม Component |
| `space-3` | 24px | Padding Card และ Gap ระหว่างกลุ่ม |
| `space-4` | 32px | Padding Summary / Modal |
| `space-5` | 40px | ระยะเริ่มต้นเนื้อหาหน้า |
| `space-6` | 48px | ขอบซ้าย–ขวา Navigation / Page |
| `space-7` | 56px | ระยะส่วนท้ายของหน้าวิเคราะห์ |
| `space-8` | 64px | ระยะส่วนท้ายของหน้าการเช่า |

- Padding, Margin และ Gap ของ Layout ใช้เฉพาะค่าจาก Spacing Scale
- Navigation สูง `88px`
- Container กว้าง `min(1880px, 100% - 96px)` และจัดกึ่งกลาง
- Layout ใช้ CSS Grid / Flexbox และคงลำดับอ่านจากซ้ายไปขวา
- Topic Selector แสดง 5 หัวข้อและ Active ได้ครั้งละ 1 หัวข้อ **(R-01)**
- Filter Area แสดง Control ที่เกี่ยวข้องเพียงกลุ่มเดียว **(R-02)**
- Action Menu วางใต้ปุ่มสามจุดของแถวนั้น **(R-04)**
- Radius: Small `12px`, Medium `16px`, Large `24px`

## Section 4 — Components & States

### Button

| Variant | Default | Hover | Focus | Disabled | Loading |
|---|---|---|---|---|---|
| Primary | พื้น `color.primary` | ยกขึ้นเล็กน้อย + Shadow | Focus Ring `color.badge.text` | Opacity 68–72% และห้ามกด | Spinner + “กำลังค้นหา” **(R-03)** |
| Secondary | พื้น `color.surface.pastel` | Shadow อ่อน | Focus Ring | Opacity 68–72% | ใช้เมื่อคำสั่งต้องรอ |
| Danger | พื้น `color.danger` | Shadow อ่อน | Focus Ring | Opacity 68–72% | Spinner + “กำลังลบ” **(R-05)** |

ขนาดมาตรฐาน: สูง `52–56px`, Padding แนวนอน `24px`, Radius `16px`

### Input / Select

| State | รูปแบบ |
|---|---|
| Default | พื้น `color.surface`, Border `color.border`, Text `color.text.primary` |
| Hover | Border `color.primary` |
| Focus | Border `color.primary` + Ring `color.surface.pastel` |
| Disabled | พื้น `color.surface.pastel`, Text `color.text.secondary`, Cursor not-allowed |
| Error | Border และข้อความ `color.danger`; ข้อความต้องระบุสาเหตุและวิธีแก้ |

### Topic Selector **(R-01, R-02)**

- Default: พื้น `color.surface.pastel`, Text `color.text.secondary`
- Active: พื้น `color.primary`, Text `color.surface`, เลือกได้ครั้งละ 1 หัวข้อ
- เมื่อเปลี่ยนหัวข้อ ต้องเปลี่ยน Title, Description, Label และ Control ให้สัมพันธ์กันทันที

### Search Result **(R-03)**

- Search Button แสดง Loading ระหว่างประมวลผล
- Result Header ระบุจำนวนรายการ
- Reset แสดง Empty State และย้าย Focus กลับไปช่องค้นหา
- Toast ใช้ยืนยันผลการทำงานด้วยภาษาไทย

### Action Menu **(R-04)**

- แถวละ 1 ปุ่มสามจุด ขนาด `48 × 48px`
- เมนูเรียง แก้ไข → ตรวจรถคืน → Divider → ลบรายการ
- ลบรายการใช้ `color.danger`; คำสั่งทั่วไปใช้ `color.text.primary`
- เปิดครั้งละ 1 เมนู และปิดด้วยคลิกภายนอกหรือ Escape

### Confirmation Modal **(R-05)**

- Surface ใช้ `color.surface`; Backdrop ใช้ `color.overlay`
- หัวข้อ “ยืนยันการลบรายการ”
- แสดงรหัสการเช่าจริงของแถวที่เลือก
- ข้อความ “การลบรายการนี้ไม่สามารถย้อนกลับได้” ใช้ `color.danger`
- ปุ่มยกเลิกเป็น Secondary; ปุ่มลบเป็น Danger
- ต้องรองรับ Cancel, Escape, คลิก Backdrop และ Focus Trap
- ลบแถวและอัปเดตจำนวนรายการหลังยืนยันเท่านั้น

### Status Badge

- พื้น `color.badge.background`
- ข้อความ `color.badge.text`
- Radius แบบ Pill และข้อความต้องอ่านได้โดยไม่พึ่งสีเพียงอย่างเดียว

## Section 5 — Voice & Tone

**บุคลิก:** สุภาพ · เป็นธรรมชาติ · กระชับ · บอกสาเหตุและทางเลือกต่อไป

| สถานการณ์ | โครงสร้างข้อความ | ตัวอย่างสุดท้าย |
|---|---|---|
| Error | สาเหตุ + สิ่งที่ต้องทำ | “กรุณากรอกรหัสหรือชื่อลูกค้าก่อนค้นหา” |
| Empty | สิ่งที่พบ + ทางเลือก | “ไม่พบรายการเช่าที่ค้นหา ลองเปลี่ยนคำค้นหาหรือล้างตัวกรอง” |
| Loading | กำลัง + คำกริยา | “กำลังค้นหา” / “กำลังลบ” |
| Success | ผลลัพธ์ + รหัสที่เกี่ยวข้อง | “ลบรายการ RENT052 เรียบร้อยแล้ว” |
| Destructive | คำถามยืนยัน + ผลกระทบ | “ต้องการลบรายการเช่า RENT052 หรือไม่? การลบรายการนี้ไม่สามารถย้อนกลับได้” **(R-05)** |

## Final Rule Traceability

| Rule | Token / Component ที่รองรับ | ผล |
|---|---|---|
| R-01 | `color.primary`, Topic Selector, Layout | ผ่าน |
| R-02 | Contextual Filter, 8px Layout | ผ่าน |
| R-03 | Button Loading, Result Count, Empty State, Toast | ผ่าน |
| R-04 | Action Menu, Divider, Menu States | ผ่าน |
| R-05 | `color.danger`, `color.overlay`, Confirmation Modal | ผ่าน |

## CSS Variable Reference

```css
:root {
  --nav: #332A46;
  --primary: #6B55B4;
  --background: #F6F2FC;
  --surface: #FFFDFF;
  --text: #342D40;
  --muted: #6F667A;
  --border: #DED5EC;
  --pastel: #EEE9FA;
  --badge-bg: #EFE7F8;
  --badge-text: #67468F;
  --danger: #A53D3D;
}
```

## Section 6 — #2B Implementation Addendum

Dry-run พบว่า `design.md` ระบุสี Focus Ring แต่ยังไม่ระบุรูปทรงที่ตรวจสอบได้ จึงเพิ่ม token หลัง Human Checkpoint:

| Token | ค่า | ใช้กับ |
|---|---:|---|
| `focus.ring.width` | `3px` | ความหนาเส้น Focus Ring |
| `focus.ring.offset` | `3px` | ระยะห่าง Focus Ring จากขอบ Component |

> ค่าทั้งสองเป็นเรขาคณิตของเส้น Focus ไม่ใช่ Layout spacing จึงไม่อยู่ภายใต้ 8px spacing scale

## CHANGE_LOG

| วันที่ | รอบ | การเปลี่ยนแปลง | เหตุผล | ผู้ตัดสิน |
|---|---|---|---|---|
| 10 กันยายน 2569 | #2B Drift Audit | เพิ่ม `focus.ring.width = 3px` และ `focus.ring.offset = 3px` | ทำให้สถานะ Focus ตรวจสอบได้และใช้ซ้ำได้โดยไม่เดาค่า | Human-approved |

## Section 7 — Final Iteration จากผลทดสอบ HTS 3

เพิ่มวันที่ 6 พฤศจิกายน 2569 สำหรับ F-01 และ F-02 ใน FORM 6 ตารางที่ 4
R-06 และ R-07 เป็นกฎใหม่จากผลทดสอบรอบนี้ ไม่ใช่กฎที่มีอยู่ตอน Audit HTS 1
คง R-01 ถึง R-05 และค่าของ token เดิมทุกค่า

| Rule | Finding | ผลที่ต้องเกิดบนหน้าจอ | วิธีตรวจ |
|---|---|---|---|
| R-06 | F-01 ผู้ทดสอบ 5/8 คนต้องรับคำใบ้ใน Task 1 | แผงลงทะเบียนมีปุ่มม่วงเข้ม “เพิ่มลูกค้าใหม่” พร้อมคำอธิบายสั้น ปุ่มเปิดฟอร์มเดิมและชื่อฟอร์มตรงกัน | เปิด customers.php ทั้งจอกว้างและจอแคบ กดปุ่มและตรวจหัวข้อฟอร์ม ช่องข้อมูล และการบันทึกจริง |
| R-07 | F-02 ผู้ทดสอบ 6/8 คนต้องรับคำใบ้ใน Task 2 | ราคาค่าเช่าต่อวันเป็นปุ่มข้อความขีดเส้นใต้พร้อมไอคอนดินสอ ชื่อปุ่มสำหรับโปรแกรมอ่านหน้าจอระบุราคาและรหัสรถ เปิดฟอร์มแยกที่มีเฉพาะช่องค่าเช่าต่อวันและให้โฟกัสช่องนั้น | เลือกรถสองคันสลับกัน ตรวจรหัสและข้อมูลตรงแถว ตรวจ focus แก้ราคาแล้วบันทึกและโหลดหน้าใหม่ ตรวจว่าข้อมูลอื่นและรถคันอื่นคงเดิม |

ใช้ color.nav กับปุ่มเพิ่มลูกค้า, color.primary กับตัวราคาที่กดได้ และ color.surface, color.badge.text, space-1 ถึง space-4 กับองค์ประกอบเดิม

ฉบับล่าสุดวันที่ 7 พฤศจิกายน 2569: หน้าลูกค้าใช้แผงเดิมและเปลี่ยนสีปุ่มเพิ่มลูกค้าเป็นม่วงเข้มตาม color.nav ส่วนหน้ารถรวมการค้นหาและตัวกรองไว้เหนือตาราง ตัดแผงและปุ่มแก้ค่าเช่าแยกออก วางตัวราคาที่กดได้ต่อจากทะเบียน และแสดงรหัสรถกับทะเบียนในฟอร์มเมื่อกดราคา
ฟอร์มลูกค้าปิดคำแนะนำการกรอกอัตโนมัติและซ่อนไอคอนรายชื่อของ Safari โดยช่องเบอร์โทรและเลขบัตรยังเก็บเป็นข้อความเพื่อรักษาเลข 0 ด้านหน้า CSS ใช้เฉพาะในหน้าลูกค้าและหน้ารถยนต์
กดตัวราคาเพื่อเปิดฟอร์มแยกที่มีเฉพาะช่องค่าเช่าต่อวัน พร้อมรหัสและทะเบียนรถสำหรับตรวจสอบ ปุ่ม “แก้ไข” ทางขวายังเปิดฟอร์มข้อมูลรถแบบเต็ม
ฟอร์มราคาใช้ POST แยกสำหรับอัปเดตเฉพาะ daily_rate ของรถคันที่เลือก ส่วนการบันทึกข้อมูลรถแบบเต็ม โครงสร้างฐานข้อมูล และวิธีคำนวณค่าเช่าคงเดิม

### สมมติฐานหลังแก้
F-01 คาดว่าจะช่วยให้เริ่มเพิ่มลูกค้าเองได้ สัมพันธ์กับ SUS ข้อ 4 และ 3
F-02 คาดว่าจะช่วยให้พบทางเข้าแก้ราคาและช่องราคา สัมพันธ์กับ SUS ข้อ 4 และ 10
ไม่มี SUS รอบสอง จึงยังไม่สรุปว่าคะแนนหรืออัตราทำงานสำเร็จดีขึ้น
