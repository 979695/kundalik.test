# 🌟 Smart Life Assistant (Telegram Bot)

Bu loyiha Laravel frameworkida qurilgan, foydalanuvchilarga kundalik vaqtini unumli boshqarish, vazifalarini rejalashtirish va odatlarini kuzatib borishda yordam beruvchi aqlli Telegram bot tizimidir.

## 🚀 Asosiy funksiyalar

-   **📋 Vazifalar boshqaruvi:** Kunlik vazifalarni vaqt bo'yicha rejalashtirish va kuzatish.
-   **📈 Odatlar trekeri:** Kunlik odatlarni shakllantirish va ularning davomiyligini (streak) hisoblash.
-   **🤖 Smart Reja (AI):** Foydalanuvchi uchun avtomatik tarzda kunlik optimal ish tartibini ishlab chiqish.
-   **🔔 Eslatmalar:** Muhim vazifalar haqida Telegram orqali ogohlantirishlar.
-   **📝 Kunlik yozuvlar:** O'z fikrlaringiz va kundalik xotiralaringizni saqlab borish imkoniyati.

## 🛠 Texnologiyalar

-   **Backend:** PHP 8.2+ & Laravel 10/11
-   **Database:** MySQL / PostgreSQL
-   **API:** Telegram Bot API
-   **Arxitektura:** Clean Architecture (Service Pattern)

## 📦 O'rnatish

1.  Repozitoriyani klonlash:
    ```bash
    git clone [repo-url]
    ```
2.  Bog'liqliklarni o'rnatish:
    ```bash
    composer install
    npm install && npm run dev
    ```
3.  `.env` faylini sozlash:
    ```bash
    cp .env.example .env
    # TELEGRAM_BOT_TOKEN ni kiriting
    ```
4.  Bazani sozlash:
    ```bash
    php artisan migrate
    ```
5.  Botni ishga tushirish (Polling):
    ```bash
    php artisan bot:run
    ```

## 🤖 Botdan foydalanish

Botni ishga tushirganingizdan so'ng `/start` buyrug'ini bering. Menyu tugmalari orqali barcha funksiyalardan foydalanishingiz mumkin:
-   **Vazifa qo'shish:** `/add 14:30 | Kitob o'qish`
-   **Vazifalar ro'yxati:** Rejalashtirilgan ishlar
-   **Odatlarim:** Streaklar va natijalar

---
*Loyiha ta'lim va shaxsiy rivojlanish maqsadida yaratilgan.*
