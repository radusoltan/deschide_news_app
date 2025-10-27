'use client';

import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Autoplay } from 'swiper/modules';
import Image from 'next/image';
import Link from 'next/link';
import 'swiper/css';
import 'swiper/css/navigation';

interface NewsSliderProps {
  title: string;
  backgroundImage?: string;
  locale: string;
}

export default function NewsSlider({ title, backgroundImage, locale }: NewsSliderProps) {
  const slides = [
    {
      id: 1,
      image: '/tailnews/dummy/img13.jpg',
      title: '5 Tips to Save Money Booking Your Next Hotel Room',
      category: 'American',
    },
    {
      id: 2,
      image: '/tailnews/dummy/img14.jpg',
      title: 'Best Beaches in America for Summer Vacation',
      category: 'American',
    },
    {
      id: 3,
      image: '/tailnews/dummy/img15.jpg',
      title: 'Top 10 American Cities to Visit This Year',
      category: 'American',
    },
    {
      id: 4,
      image: '/tailnews/dummy/img16.jpg',
      title: 'Discover the Best American Cuisine',
      category: 'American',
    },
    {
      id: 5,
      image: '/tailnews/dummy/img17.jpg',
      title: 'American Art Museums You Must Visit',
      category: 'American',
    },
    {
      id: 6,
      image: '/tailnews/dummy/img18.jpg',
      title: 'Adventure Activities Across America',
      category: 'American',
    },
  ];

  return (
    <div
      className="relative bg-gray-50 bg-cover bg-center bg-fixed"
      style={backgroundImage ? { backgroundImage: `url(${backgroundImage})` } : undefined}
    >
      <div className="bg-black bg-opacity-60">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            <div className="flex-shrink max-w-full w-full py-12 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-white text-2xl font-bold text-shadow-black">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>
                  {title}
                </h2>
              </div>

              <Swiper
                modules={[Navigation, Autoplay]}
                spaceBetween={0}
                slidesPerView={1}
                navigation
                autoplay={{ delay: 5000, disableOnInteraction: false }}
                breakpoints={{
                  640: {
                    slidesPerView: 2,
                    spaceBetween: 0,
                  },
                  768: {
                    slidesPerView: 3,
                    spaceBetween: 0,
                  },
                  1024: {
                    slidesPerView: 4,
                    spaceBetween: 0,
                  },
                }}
                className="news-slider"
              >
                {slides.map((slide) => (
                  <SwiperSlide key={slide.id}>
                    <div className="w-full pb-3 px-2">
                      <div className="hover-img bg-white">
                        <Link href={`/${locale}/article/${slide.id}`}>
                          <Image
                            className="max-w-full w-full mx-auto h-auto"
                            src={slide.image}
                            alt={slide.title}
                            width={400}
                            height={300}
                          />
                        </Link>
                        <div className="py-3 px-6">
                          <h3 className="text-lg font-bold leading-tight mb-2">
                            <Link href={`/${locale}/article/${slide.id}`}>{slide.title}</Link>
                          </h3>
                          <Link className="text-gray-500" href={`/${locale}/category/american`}>
                            <span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>
                            {slide.category}
                          </Link>
                        </div>
                      </div>
                    </div>
                  </SwiperSlide>
                ))}
              </Swiper>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
