'use client';

import { useState } from 'react';
import { Metadata } from 'next';

interface ContactPageProps {
  params: Promise<{
    locale: string;
  }>;
}

/**
 * Contact Page
 *
 * Contact form for readers to get in touch with the newsroom
 * Includes general inquiries, press releases, and tip submissions
 */
export default function ContactPage({ params }: ContactPageProps) {
  const [locale, setLocale] = useState('ro');
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    subject: '',
    category: 'general',
    message: '',
  });
  const [status, setStatus] = useState<'idle' | 'sending' | 'success' | 'error'>('idle');

  // Load locale from params
  useState(() => {
    params.then(({ locale: l }) => setLocale(l));
  });

  const content = {
    ro: {
      title: 'Contactează-ne',
      subtitle: 'Suntem aici pentru a te asculta',
      form: {
        name: 'Nume complet',
        email: 'Email',
        subject: 'Subiect',
        category: 'Categorie',
        message: 'Mesaj',
        submit: 'Trimite mesaj',
        sending: 'Se trimite...',
        categories: {
          general: 'Întrebări generale',
          press: 'Comunicate de presă',
          tip: 'Sugerează o știre',
          feedback: 'Feedback',
          technical: 'Probleme tehnice',
        },
      },
      info: {
        title: 'Informații de contact',
        email: 'redactie@deschide.md',
        address: 'Chișinău, Moldova',
        workingHours: 'Program: Luni - Vineri, 09:00 - 18:00',
      },
      success: 'Mesajul tău a fost trimis cu succes! Îți vom răspunde în curând.',
      error: 'A apărut o eroare. Te rugăm să încerci din nou sau să ne scrii direct la email.',
    },
    en: {
      title: 'Contact Us',
      subtitle: "We're here to listen",
      form: {
        name: 'Full name',
        email: 'Email',
        subject: 'Subject',
        category: 'Category',
        message: 'Message',
        submit: 'Send message',
        sending: 'Sending...',
        categories: {
          general: 'General inquiries',
          press: 'Press releases',
          tip: 'News tip',
          feedback: 'Feedback',
          technical: 'Technical issues',
        },
      },
      info: {
        title: 'Contact information',
        email: 'redactie@deschide.md',
        address: 'Chișinău, Moldova',
        workingHours: 'Hours: Monday - Friday, 09:00 - 18:00',
      },
      success: 'Your message has been sent successfully! We will respond soon.',
      error: 'An error occurred. Please try again or write to us directly via email.',
    },
    ru: {
      title: 'Свяжитесь с Нами',
      subtitle: 'Мы здесь, чтобы вас выслушать',
      form: {
        name: 'Полное имя',
        email: 'Email',
        subject: 'Тема',
        category: 'Категория',
        message: 'Сообщение',
        submit: 'Отправить сообщение',
        sending: 'Отправка...',
        categories: {
          general: 'Общие вопросы',
          press: 'Пресс-релизы',
          tip: 'Предложить новость',
          feedback: 'Отзыв',
          technical: 'Технические проблемы',
        },
      },
      info: {
        title: 'Контактная информация',
        email: 'redactie@deschide.md',
        address: 'Кишинёв, Молдова',
        workingHours: 'Часы работы: Понедельник - Пятница, 09:00 - 18:00',
      },
      success: 'Ваше сообщение успешно отправлено! Мы ответим в ближайшее время.',
      error:
        'Произошла ошибка. Пожалуйста, попробуйте еще раз или напишите нам напрямую по электронной почте.',
    },
  };

  const t = content[locale as keyof typeof content] || content.ro;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus('sending');

    try {
      // TODO: Implement actual email sending via API
      // For now, simulate success
      await new Promise((resolve) => setTimeout(resolve, 1500));

      setStatus('success');
      setFormData({
        name: '',
        email: '',
        subject: '',
        category: 'general',
        message: '',
      });

      // Reset status after 5 seconds
      setTimeout(() => setStatus('idle'), 5000);
    } catch (error) {
      console.error('Error sending message:', error);
      setStatus('error');

      // Reset status after 5 seconds
      setTimeout(() => setStatus('idle'), 5000);
    }
  };

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-6xl">
      {/* Page Header */}
      <div className="mb-12 text-center">
        <h1 className="text-5xl font-bold text-gray-900 dark:text-white mb-4">{t.title}</h1>
        <p className="text-xl text-gray-600 dark:text-gray-400">{t.subtitle}</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Contact Form */}
        <div className="lg:col-span-2">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Name */}
              <div>
                <label
                  htmlFor="name"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  {t.form.name} *
                </label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-deschide-tomato focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
              </div>

              {/* Email */}
              <div>
                <label
                  htmlFor="email"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  {t.form.email} *
                </label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-deschide-tomato focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
              </div>

              {/* Category */}
              <div>
                <label
                  htmlFor="category"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  {t.form.category} *
                </label>
                <select
                  id="category"
                  name="category"
                  value={formData.category}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-deschide-tomato focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="general">{t.form.categories.general}</option>
                  <option value="press">{t.form.categories.press}</option>
                  <option value="tip">{t.form.categories.tip}</option>
                  <option value="feedback">{t.form.categories.feedback}</option>
                  <option value="technical">{t.form.categories.technical}</option>
                </select>
              </div>

              {/* Subject */}
              <div>
                <label
                  htmlFor="subject"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  {t.form.subject} *
                </label>
                <input
                  type="text"
                  id="subject"
                  name="subject"
                  value={formData.subject}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-deschide-tomato focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
              </div>

              {/* Message */}
              <div>
                <label
                  htmlFor="message"
                  className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  {t.form.message} *
                </label>
                <textarea
                  id="message"
                  name="message"
                  value={formData.message}
                  onChange={handleChange}
                  required
                  rows={6}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-deschide-tomato focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white resize-none"
                />
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                disabled={status === 'sending'}
                className="w-full px-6 py-3 bg-deschide-tomato text-white rounded-lg hover:bg-deschide-tomato-dark disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors font-medium text-lg"
              >
                {status === 'sending' ? t.form.sending : t.form.submit}
              </button>

              {/* Status Messages */}
              {status === 'success' && (
                <div className="bg-green-50 border-l-4 border-green-600 p-4 rounded">
                  <p className="text-green-800">{t.success}</p>
                </div>
              )}

              {status === 'error' && (
                <div className="bg-red-50 border-l-4 border-deschide-tomato p-4 rounded">
                  <p className="text-red-800">{t.error}</p>
                </div>
              )}
            </form>
          </div>
        </div>

        {/* Contact Information */}
        <div className="space-y-6">
          {/* Info Card */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
              {t.info.title}
            </h2>

            <div className="space-y-4">
              {/* Email */}
              <div className="flex items-start gap-3">
                <svg
                  className="w-6 h-6 text-deschide-tomato mt-1"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                  />
                </svg>
                <div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">Email</div>
                  <a
                    href={`mailto:${t.info.email}`}
                    className="text-deschide-tomato hover:text-deschide-tomato-dark font-medium"
                  >
                    {t.info.email}
                  </a>
                </div>
              </div>

              {/* Address */}
              <div className="flex items-start gap-3">
                <svg
                  className="w-6 h-6 text-deschide-tomato mt-1"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                  />
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>
                <div>
                  <div className="text-sm text-gray-500 dark:text-gray-400">
                    {locale === 'ro' && 'Adresă'}
                    {locale === 'en' && 'Address'}
                    {locale === 'ru' && 'Адрес'}
                  </div>
                  <div className="text-gray-900 dark:text-white font-medium">
                    {t.info.address}
                  </div>
                </div>
              </div>

              {/* Working Hours */}
              <div className="flex items-start gap-3">
                <svg
                  className="w-6 h-6 text-deschide-tomato mt-1"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <div>
                  <div className="text-gray-900 dark:text-white font-medium">
                    {t.info.workingHours}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Social Media (Placeholder) */}
          <div className="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4">
              {locale === 'ro' && 'Urmărește-ne'}
              {locale === 'en' && 'Follow Us'}
              {locale === 'ru' && 'Следите за Нами'}
            </h3>
            <div className="flex gap-4">
              {/* Placeholder for social media icons */}
              <a
                href="#"
                className="w-10 h-10 bg-deschide-tomato rounded-full flex items-center justify-center text-white hover:bg-deschide-tomato-dark"
              >
                <span className="sr-only">Facebook</span>F
              </a>
              <a
                href="#"
                className="w-10 h-10 bg-deschide-tomato rounded-full flex items-center justify-center text-white hover:bg-deschide-tomato-dark"
              >
                <span className="sr-only">Twitter</span>T
              </a>
              <a
                href="#"
                className="w-10 h-10 bg-deschide-tomato rounded-full flex items-center justify-center text-white hover:bg-deschide-tomato-dark"
              >
                <span className="sr-only">Instagram</span>I
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
