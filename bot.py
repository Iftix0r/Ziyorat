import asyncio
import json
import os
import re
from datetime import datetime
from dotenv import load_dotenv
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, ReplyKeyboardRemove

load_dotenv()

BOT_TOKEN = os.environ["BOT_TOKEN"]
ADMIN_GROUP_ID = int(os.environ["ADMIN_GROUP_ID"])

bot = Bot(token=BOT_TOKEN)
dp = Dispatcher()

os.makedirs("users", exist_ok=True)


def safe_user_path(user_id: int) -> str:
    safe_id = re.sub(r"[^0-9]", "", str(user_id))
    path = os.path.realpath(f"users/{safe_id}.json")
    if not path.startswith(os.path.realpath("users")):
        raise ValueError("Invalid user_id")
    return path


def get_user(user_id: int) -> dict:
    path = safe_user_path(user_id)
    if os.path.exists(path):
        with open(path) as f:
            return json.load(f)
    return {}


def save_user(user_id: int, data: dict):
    with open(safe_user_path(user_id), "w") as f:
        json.dump(data, f)


@dp.message(Command("start"))
async def start(msg: types.Message):
    save_user(msg.from_user.id, {"step": "waiting_destination"})
    await msg.answer(
        "🚖 *Taxi Bot*ga xush kelibsiz!\n\n🎯 Chiqish va borish manzilingizni yozing:\n\n_Masalan: Samarqand - Farg'ona va hokozo_",
        parse_mode="Markdown",
        reply_markup=ReplyKeyboardRemove(),
    )


@dp.message(F.contact)
async def handle_contact(msg: types.Message):
    data = get_user(msg.from_user.id)
    if data.get("step") == "waiting_phone":
        await process_order(msg, data, msg.contact.phone_number)


@dp.message()
async def handle_text(msg: types.Message):
    user_id = msg.from_user.id
    text = msg.text or ""
    data = get_user(user_id)
    step = data.get("step")

    if text == "🚖 Yana taxi chaqirish":
        save_user(user_id, {"step": "waiting_destination"})
        await msg.answer("🎯 Chiqish va borish manzilingizni yozing:", reply_markup=ReplyKeyboardRemove())

    elif step == "waiting_destination" and text:
        data["destination"] = text
        data["step"] = "waiting_phone"
        save_user(user_id, data)

        kb = ReplyKeyboardMarkup(
            keyboard=[[KeyboardButton(text="📱 Telefon raqamni yuborish", request_contact=True)]],
            resize_keyboard=True,
            one_time_keyboard=True,
        )
        await msg.answer(
            f"✅ Borish joyi: *{text}*\n\n📱 Telefon raqamingizni yuboring: masalan +998999558657",
            parse_mode="Markdown",
            reply_markup=kb,
        )

    elif step == "waiting_phone" and text:
        await process_order(msg, data, text)


async def process_order(msg: types.Message, data: dict, phone: str):
    user_id = msg.from_user.id
    user_name = msg.from_user.first_name or "Foydalanuvchi"

    data["phone"] = phone
    data["step"] = "completed"
    save_user(user_id, data)

    order_text = (
        f"🚖 *YANGI TAXI BUYURTMA*\n\n"
        f"👤 *Mijoz:* [{user_name}](tg://user?id={user_id})\n"
        f"📱 *Telefon:* {phone}\n"
        f"🎯 *Qayerga:* {data['destination']}\n"
        f"🕐 *Vaqt:* {datetime.now().strftime('%d.%m.%Y %H:%M')}\n"
        f"🆔 *User ID:* {user_id}"
    )
    await bot.send_message(ADMIN_GROUP_ID, order_text, parse_mode="Markdown")

    kb = ReplyKeyboardMarkup(
        keyboard=[[KeyboardButton(text="🚖 Yana taxi chaqirish")]],
        resize_keyboard=True,
    )
    await msg.answer(
        f"✅ *Buyurtmangiz qabul qilindi!*\n\nTez orada haydovchi siz bilan bog'lanadi.\n\n📞 Telefon: {phone}\n🎯 Qayerga: {data['destination']}",
        parse_mode="Markdown",
        reply_markup=kb,
    )


async def main():
    await dp.start_polling(bot)


if __name__ == "__main__":
    asyncio.run(main())
