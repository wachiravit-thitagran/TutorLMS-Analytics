# Changelog

## 2.0.0 — Tutor LMS 4.0 support + dashboard overhaul

รอบนี้ทำครบทุกข้อเสนอแนะ ทั้งสถิติใหม่จาก Tutor LMS 4.0 และการปรับปรุง UI

### สถิติใหม่ (Tutor LMS 4.0)

เพิ่ม data provider ใหม่ 9 ตัว โดยทุกตัวตรวจสอบว่าตาราง/addon มีอยู่จริงก่อน และคืนค่าว่างอย่างปลอดภัยเมื่อไม่มีข้อมูล (ไม่ fatal):

- **Monetization_Provider** — รองรับ Native eCommerce (`tutor_orders`): รายได้รวม/สุทธิ, refund rate, แนวโน้มรายวัน, แยกตามประเภทคำสั่งซื้อ, การใช้คูปอง, ที่มาการลงทะเบียน (bundle/สมาชิก/ในระบบ/ภายนอก/เพิ่มเอง), และสัญญาณการซื้อเป็นของขวัญ
- **Subscription_Provider** — สมาชิกที่ใช้งาน, churn, สมัครใหม่/ต่ออายุ, MRR โดยประมาณ, รายได้ต่อแพลน (ตรวจ column ของ `tutor_subscriptions` ที่ runtime เพราะ schema Pro ไม่คงที่)
- **Bundle_Provider** — ยอดขาย/ผู้เรียนต่อ Course Bundle
- **QnA_Provider** — คำถามทั้งหมด, ค้างตอบ, อัตราการตอบ, เวลาตอบครั้งแรกเฉลี่ย, คำถามค้างตอบล่าสุด
- **Certificate_Provider** — ใบรับรองที่ออก, อัตราจบ→ได้ใบรับรอง, แนวโน้มรายเดือน
- **Quiz_Type_Provider** — สถิติแยกตามประเภทคำถาม + adoption ของ 5 ประเภทใหม่ใน 4.0 (draw_image, pin_image, coordinates, scale, puzzle)
- **Live_Lesson_Provider** — Zoom / Google Meet: จำนวน, ที่จัดไปแล้ว, ที่กำลังจะมาถึง
- **Gradebook_Provider** — การกระจายเกรด + ค่าเฉลี่ย (addon Gradebook)
- **Assignment_Provider** — งานที่มอบหมาย: ส่งแล้ว/รอตรวจ, คะแนนเฉลี่ย, pass rate, เวลาตรวจเฉลี่ย

### การปรับปรุง UI/UX

1. **Lazy loading** — แต่ละแท็บดึงข้อมูลผ่าน REST เมื่อเปิดครั้งแรกเท่านั้น (เดิมรันทุก provider ทุกครั้งที่โหลดหน้า) + cache ด้วย transient
2. **ยุบแท็บ** — หน้าคอร์สจาก 10 เหลือ 7, หน้าภาพรวมเหลือ 4 กลุ่มที่ชัดเจน
3. **ตัวเลือกช่วงเวลากลาง** — date picker + ปุ่มลัด 7/30/90 วัน ใช้กับทุกกราฟ (เลิก hardcode)
4. **KPI cards มี context** — แสดง % เปลี่ยนแปลงเทียบช่วงก่อนหน้า (▲▼) + แก้บั๊กการ์ด "คะแนนรีวิวเฉลี่ย" ที่เคยแสดงคำว่า `Active`
5. **คำอธิบายใต้กราฟ** — ทุกกราฟมีคำอธิบายสั้น ๆ ว่าดูอะไรและควรทำอะไรต่อ
6. **Empty / loading / error states** — สม่ำเสมอทุกที่ (skeleton, ข้อความว่าง, ปุ่มลองใหม่)
7. **ตาราง** — ค้นหา + จัดเรียงทุกคอลัมน์ + แบ่งหน้า (เดิม render ทุกแถวใน DOM แล้วซ่อน)
8. **เลิกใช้ CDN** — Chart.js pin เวอร์ชันและ vendored ในปลั๊กอิน, CSS เขียนเองแบบ scoped (เลิก Tailwind Play CDN), แก้ปัญหา script โหลดซ้ำ
9. **i18n** — ทุกข้อความ wrap ด้วย `__()` + โหลด text domain + ไฟล์ `.pot` และคำแปลอังกฤษ (`en_US`)
10. **Accessibility** — แท็บใช้ ARIA + คีย์บอร์ด (ลูกศร/Home/End), สีกราฟ color-blind-safe (Okabe–Ito), รองรับ `prefers-reduced-motion`

### ความปลอดภัย

- REST `/track` เพิ่มการตรวจ nonce + rate limit (เดิมเปิดสาธารณะเต็มที่)
- ลิงก์ส่งออก CSV เพิ่ม nonce และ BOM UTF-8 (Excel อ่านภาษาไทยถูก)
- endpoint `/section` ใหม่จำกัดสิทธิ์ผู้ดูแล/ผู้สอนเท่านั้น

### สถาปัตยกรรม

- `Analytics_Service` เป็นตัวกลาง map "section" → providers + cache + date range
- `Date_Range`, `Stats_Cache`, `Tutor_Schema` เป็นชั้นโครงสร้างพื้นฐานใหม่
- Views เปลี่ยนเป็น shell ที่ให้ JS render จาก JSON — โค้ดสั้นลงและ maintain ง่ายขึ้น

### หมายเหตุ / ข้อจำกัด

- Tutor LMS **ไม่มี**ฟีเจอร์ Lesson Notes ในโค้ดฟรี 4.0.1 และไม่มี Jitsi — จึงไม่ได้ทำสถิติสองส่วนนี้
- Bundle expiry (4.0 Pro) ไม่มี meta key เปิดเผยใน source ฟรี — ยังไม่รองรับ
- ควรทดสอบกับเว็บ Tutor LMS 4.0 จริงก่อนใช้งานจริง เพราะ schema บางส่วน (โดยเฉพาะ subscription/gradebook ที่เป็น Pro) ตรวจที่ runtime แต่ค่าคอลัมน์บางตัวยังไม่ได้ยืนยันกับฐานข้อมูลจริง

### การทดสอบ

- `tests/smoke-runtime.php` — 34 assertions ครอบคลุม provider ใหม่ทั้งหมด (รันได้บน PHP CLI หรือ php-wasm โดยไม่ต้องมี WordPress)
- ชุดทดสอบเดิมทั้งหมดผ่าน (ปรับ test ที่เคยตรวจ HTML ฝั่ง server ให้ตรวจ shell + payload แบบใหม่)
