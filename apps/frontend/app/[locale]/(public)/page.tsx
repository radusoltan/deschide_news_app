import type { Metadata } from 'next'
import Image from 'next/image'
import Link from 'next/link'
import NewsSlider from './components/NewsSlider'

export const metadata: Metadata = {
  title: 'Acasă',
  description: 'Portal de știri în limba română',
}

interface PageProps {
  params: Promise<{ locale: string }>
}

export default async function HomePage({ params }: PageProps) {
  const { locale } = await params

  return (
    <main id="content">
      {/* Hero Big Grid */}
      <div className="bg-white py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left Cover - Main Story */}
            <div className="flex-shrink max-w-full w-full lg:w-1/2 pb-1 lg:pb-0 lg:pr-1">
              <div className="relative hover-img max-h-98 overflow-hidden">
                <Link href={`/${locale}/article/1`}>
                  <Image
                    className="max-w-full w-full mx-auto h-auto"
                    src="/tailnews/dummy/img1.jpg"
                    alt="Amazon Shoppers"
                    width={1600}
                    height={900}
                    priority
                  />
                </Link>
                <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
                  <Link href={`/${locale}/article/1`}>
                    <h2 className="text-3xl font-bold capitalize text-white mb-3">Amazon Shoppers Are Ditching Designer Belts for This Best-Selling</h2>
                  </Link>
                  <p className="text-gray-100 hidden sm:inline-block">This is a wider card with supporting text below as a natural lead-in to additional content. This very helpfull for generate default content..</p>
                  <div className="pt-2">
                    <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Europe</div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Side - Grid of 4 Stories */}
            <div className="flex-shrink max-w-full w-full lg:w-1/2">
              <div className="box-one flex flex-row flex-wrap">
                <article className="flex-shrink max-w-full w-full sm:w-1/2">
                  <div className="relative hover-img max-h-48 overflow-hidden">
                    <Link href={`/${locale}/article/2`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img2.jpg"
                        alt="Technology"
                        width={1600}
                        height={900}
                        priority
                      />
                    </Link>
                    <div className="absolute px-4 pt-7 pb-4 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/2`}>
                        <h2 className="text-lg font-bold capitalize leading-tight text-white mb-1">News magazines are becoming obsolete, replaced by gadgets</h2>
                      </Link>
                      <div className="pt-1">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Techno</div>
                      </div>
                    </div>
                  </div>
                </article>

                <article className="flex-shrink max-w-full w-full sm:w-1/2">
                  <div className="relative hover-img max-h-48 overflow-hidden">
                    <Link href={`/${locale}/article/3`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img3.jpg"
                        alt="Architecture"
                        width={1600}
                        height={900}
                        priority
                      />
                    </Link>
                    <div className="absolute px-4 pt-7 pb-4 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/3`}>
                        <h2 className="text-lg font-bold capitalize leading-tight text-white mb-1">Minimalist designs are starting to be popular with the next generation</h2>
                      </Link>
                      <div className="pt-1">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Architecture</div>
                      </div>
                    </div>
                  </div>
                </article>

                <article className="flex-shrink max-w-full w-full sm:w-1/2">
                  <div className="relative hover-img max-h-48 overflow-hidden">
                    <Link href={`/${locale}/article/4`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img4.jpg"
                        alt="Interior"
                        width={1600}
                        height={900}
                        priority
                      />
                    </Link>
                    <div className="absolute px-4 pt-7 pb-4 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/4`}>
                        <h2 className="text-lg font-bold capitalize leading-tight text-white mb-1">Tips for decorating the interior of the living room</h2>
                      </Link>
                      <div className="pt-1">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Interior</div>
                      </div>
                    </div>
                  </div>
                </article>

                <article className="flex-shrink max-w-full w-full sm:w-1/2">
                  <div className="relative hover-img max-h-48 overflow-hidden">
                    <Link href={`/${locale}/article/5`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img5.jpg"
                        alt="Lifestyle"
                        width={1600}
                        height={900}
                        priority
                      />
                    </Link>
                    <div className="absolute px-4 pt-7 pb-4 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/5`}>
                        <h2 className="text-lg font-bold capitalize leading-tight text-white mb-1">Online taxi users are increasing drastically ahead of the new year</h2>
                      </Link>
                      <div className="pt-1">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Lifestyle</div>
                      </div>
                    </div>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Block News Section */}
      <div className="bg-white">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>Europe
                </h2>
              </div>
              <div className="flex flex-row flex-wrap -mx-3">
                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/6`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img6.jpg"
                        alt="Hotel"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/6`}>5 Tips to Save Money Booking Your Next Hotel Room</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">This is a wider card with supporting text below as a natural lead-in to additional content.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/7`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img7.jpg"
                        alt="Travel"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/7`}>Best Beaches in Europe for Summer Vacation</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">This is a wider card with supporting text below as a natural lead-in to additional content.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/8`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img8.jpg"
                        alt="City"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/8`}>Top 10 European Cities to Visit This Year</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">This is a wider card with supporting text below as a natural lead-in to additional content.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/9`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img9.jpg"
                        alt="Food"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/9`}>Discover the Best European Cuisine</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Explore the rich culinary traditions across European countries.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/10`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img10.jpg"
                        alt="Culture"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/10`}>European Art Museums You Must Visit</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">A guide to the most impressive art collections in Europe.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/11`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img11.jpg"
                        alt="Adventure"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/11`}>Adventure Activities Across Europe</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">From skiing in the Alps to diving in Mediterranean.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/europe`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Europe</Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Sidebar */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              <div className="w-full bg-gray-50 h-full">
                <div className="text-sm py-6 sticky top-0">
                  <div className="w-full text-center">
                    <a className="uppercase" href="#">Advertisement</a>
                    <a href="#">
                      <Image
                        className="mx-auto"
                        src="/tailnews/dummy/img12.jpg"
                        alt="advertisement area"
                        width={300}
                        height={250}
                      />
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Slider News Section */}
      <NewsSlider
        title="American"
        backgroundImage="https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&h=1080&fit=crop"
      />

      {/* Block News Section - Africa */}
      <div className="bg-white py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            <div className="flex-shrink max-w-full w-full overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>Africa
                </h2>
              </div>
              <div className="flex flex-row flex-wrap -mx-3">
                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/12`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img13.jpg"
                        alt="Safari Adventure"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/12`}>Best Safari Destinations in Africa</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Discover the most breathtaking wildlife experiences across the African continent.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/13`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img14.jpg"
                        alt="African Culture"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/13`}>Exploring Traditional African Cultures</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">A journey through the rich cultural heritage and traditions of Africa.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/14`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img15.jpg"
                        alt="African Culture"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/14`}>Exploring Traditional African Cultures</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">A journey through the rich cultural heritage and traditions of Africa.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/15`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img16.jpg"
                        alt="African Culture"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/15`}>Exploring Traditional African Cultures</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">A journey through the rich cultural heritage and traditions of Africa.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/16`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img17.jpg"
                        alt="African Landscape"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/16`}>The Most Beautiful Landscapes in Africa</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">From deserts to rainforests, explore Africa&apos;s diverse natural beauty.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/17`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img18.jpg"
                        alt="African Cuisine"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/17`}>Taste of Africa: Traditional Dishes You Must Try</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Explore the rich and diverse culinary traditions across African nations.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/18`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img19.jpg"
                        alt="African Cities"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/18`}>Top 10 African Cities to Visit This Year</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Urban adventures await in these vibrant African metropolitan areas.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 lg:w-1/4 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/19`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img20.jpg"
                        alt="African Music"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/19`}>The Sound of Africa: Music and Dance Traditions</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Experience the rhythmic heartbeat of African musical heritage.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/africa`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Africa</Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Block News Section - Asian with Sidebar */}
      <div className="bg-gray-100 py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>Asian
                </h2>
              </div>
              <div className="flex flex-row flex-wrap -mx-3">
                {/* Featured Article */}
                <div className="flex-shrink max-w-full w-full px-3 pb-5">
                  <div className="relative hover-img max-h-98 overflow-hidden">
                    <Link href={`/${locale}/article/20`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img21.jpg"
                        alt="Asian Technology"
                        width={1600}
                        height={600}
                      />
                    </Link>
                    <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/20`}>
                        <h2 className="text-3xl font-bold capitalize text-white mb-3">Tech Innovation in Asia: The Future is Here</h2>
                      </Link>
                      <p className="text-gray-100 hidden sm:inline-block">Exploring how Asian countries are leading the way in technological advancement and innovation.</p>
                      <div className="pt-2">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Asian</div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Grid Articles */}
                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/21`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img22.jpg"
                        alt="Tokyo"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/21`}>Top Destinations in Japan for 2024</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">From ancient temples to modern cities, discover Japan&apos;s must-visit places.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/22`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img23.jpg"
                        alt="Asian Cuisine"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/22`}>The Ultimate Guide to Asian Street Food</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Experience the vibrant flavors of authentic Asian street cuisine.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/23`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img24.jpg"
                        alt="Asian Culture"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/23`}>Traditional Festivals Across Asia</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Celebrate the rich cultural heritage through Asian festivals.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/24`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img25.jpg"
                        alt="Asian Business"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/24`}>Asian Markets Leading Global Economy</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">How Asian economies are shaping the future of global trade.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/25`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img26.jpg"
                        alt="Asian Nature"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/25`}>Breathtaking Natural Wonders of Asia</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">From mountains to beaches, explore Asia&apos;s stunning landscapes.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/26`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img27.jpg"
                        alt="Asian Fashion"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/26`}>Asian Fashion Trends Taking the World</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Discover how Asian designers are influencing global fashion.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/asian`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Asian</Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Sidebar */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pl-8 lg:pt-14 lg:pb-8 order-first lg:order-last">
              <div className="w-full bg-white">
                <div className="mb-6">
                  <div className="p-4 bg-gray-100">
                    <h2 className="text-lg font-bold">Most Popular</h2>
                  </div>
                  <ul className="post-number">
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/101`}>Why the world would end without political polls</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/102`}>Meet The Man Who Designed The Ducati Monster</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/103`}>2024 Audi R8 Spyder spy shots release</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/104`}>Lamborghini makes Huracán GT3 racer faster</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/105`}>ZF plans $14 billion autonomous vehicle push</Link>
                    </li>
                  </ul>
                </div>
              </div>

              <div className="text-sm py-6 sticky top-0">
                <div className="w-full text-center">
                  <a className="uppercase" href="#">Advertisement</a>
                  <a href="#">
                    <Image
                      className="mx-auto"
                      src="/tailnews/dummy/img28.jpg"
                      alt="advertisement area"
                      width={300}
                      height={250}
                    />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Block News Section - Latest News with Left Sidebar */}
      <div className="bg-gray-50 py-6">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2">
          <div className="flex flex-row flex-wrap">
            {/* Left Sidebar */}
            <div className="flex-shrink max-w-full w-full lg:w-1/3 lg:pr-8 lg:pt-14 lg:pb-8 order-first">
              <div className="w-full bg-white">
                <div className="mb-6">
                  <div className="p-4 bg-gray-100">
                    <h2 className="text-lg font-bold">Most Popular</h2>
                  </div>
                  <ul className="post-number">
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/106`}>Why the world would end without political polls</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/107`}>Meet The Man Who Designed The Ducati Monster</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/108`}>2024 Audi R8 Spyder spy shots release</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/109`}>Lamborghini makes Huracán GT3 racer faster</Link>
                    </li>
                    <li className="border-b border-gray-100 hover:bg-gray-50">
                      <Link className="text-lg font-bold px-6 py-3 flex flex-row items-center" href={`/${locale}/article/110`}>ZF plans $14 billion autonomous vehicle push</Link>
                    </li>
                  </ul>
                </div>
              </div>

              <div className="text-sm py-6 sticky top-0">
                <div className="w-full text-center">
                  <a className="uppercase" href="#">Advertisement</a>
                  <a href="#">
                    <Image
                      className="mx-auto"
                      src="/tailnews/dummy/img29.jpg"
                      alt="advertisement area"
                      width={300}
                      height={250}
                    />
                  </a>
                </div>
              </div>
            </div>

            {/* Right - Main Content */}
            <div className="flex-shrink max-w-full w-full lg:w-2/3 overflow-hidden">
              <div className="w-full py-3">
                <h2 className="text-gray-800 text-2xl font-bold">
                  <span className="inline-block h-5 border-l-3 border-red-600 mr-2"></span>Latest news
                </h2>
              </div>
              <div className="flex flex-row flex-wrap -mx-3">
                {/* Featured Article */}
                <div className="flex-shrink max-w-full w-full px-3 pb-5">
                  <div className="relative hover-img max-h-98 overflow-hidden">
                    <Link href={`/${locale}/article/27`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img30.jpg"
                        alt="Breaking News"
                        width={1600}
                        height={600}
                      />
                    </Link>
                    <div className="absolute px-5 pt-8 pb-5 bottom-0 w-full bg-gradient-cover">
                      <Link href={`/${locale}/article/27`}>
                        <h2 className="text-3xl font-bold capitalize text-white mb-3">Breaking: Major Developments in Global Technology</h2>
                      </Link>
                      <p className="text-gray-100 hidden sm:inline-block">Stay updated with the latest developments and breaking news from around the world.</p>
                      <div className="pt-2">
                        <div className="text-gray-100"><div className="inline-block h-3 border-l-2 border-red-600 mr-2"></div>Latest</div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Grid Articles */}
                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/28`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img31.jpg"
                        alt="Climate Change"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/28`}>Climate Change: New Report Highlights Urgency</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Scientists warn about accelerating climate impacts and call for immediate action.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/29`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img32.jpg"
                        alt="Space Exploration"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/29`}>NASA Announces New Mars Mission Plans</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Exciting developments in space exploration with ambitious new projects.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/30`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img33.jpg"
                        alt="Economy"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/30`}>Global Markets React to Economic Shifts</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Financial analysts discuss implications of recent economic developments.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/31`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img34.jpg"
                        alt="Health"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/31`}>Medical Breakthrough in Disease Treatment</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Researchers announce promising results in new medical trials.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/32`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img35.jpg"
                        alt="Sports"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/32`}>Championship Results and Highlights</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Recap of the most exciting moments from recent sporting events.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>

                <div className="flex-shrink max-w-full w-full sm:w-1/3 px-3 pb-3 pt-3 sm:pt-0 border-b-2 sm:border-b-0 border-dotted border-gray-100">
                  <div className="flex flex-row sm:block hover-img">
                    <Link href={`/${locale}/article/33`}>
                      <Image
                        className="max-w-full w-full mx-auto h-auto"
                        src="/tailnews/dummy/img36.jpg"
                        alt="Entertainment"
                        width={640}
                        height={427}
                      />
                    </Link>
                    <div className="py-0 sm:py-3 pl-3 sm:pl-0">
                      <h3 className="text-lg font-bold leading-tight mb-2">
                        <Link href={`/${locale}/article/33`}>New Movie Releases Breaking Box Office Records</Link>
                      </h3>
                      <p className="hidden md:block text-gray-600 leading-tight mb-1">Entertainment industry celebrates record-breaking weekend.</p>
                      <Link className="text-gray-500" href={`/${locale}/category/latest`}><span className="inline-block h-3 border-l-2 border-red-600 mr-2"></span>Latest</Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
