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
      className="relative bg-surface-sunken bg-cover bg-center bg-fixed"
      style={backgroundImage ? { backgroundImage: `url(${backgroundImage})` } : undefined}
    >
      <div className="bg-black bg-opacity-60">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            <div className="flex-shrink max-w-full w-full py-12 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-white text-2xl font-heading uppercase">
                  <span className="inline-block h-5 border-l-3 border-brand-tomato-500 mr-2"></span>
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
                {slides.map((slide, index) => (
                  <SwiperSlide key={slide.id}>
                    <div className="w-full pb-3 px-2">
                      <div className="hover-img bg-surface">
                        <Link href={`/${locale}/article/${slide.id}`}>
                          <Image
                            className="max-w-full w-full mx-auto h-auto"
                            src={slide.image}
                            alt={slide.title}
                            width={400}
                            height={300}
                            loading="lazy"
                            placeholder="blur"
                            blurDataURL="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMWYyOTM3Ii8+PC9zdmc+"
                          />
                        </Link>
                        <div className="py-3 px-6">
                          <h3 className="text-lg font-bold leading-tight mb-2">
                            <Link href={`/${locale}/article/${slide.id}`}>{slide.title}</Link>
                          </h3>
                          <Link className="text-secondary hover:text-brand-tomato-500 transition-colors" href={`/${locale}/category/american`}>
                            <span className="inline-block h-3 border-l-2 border-brand-tomato-500 mr-2"></span>
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
