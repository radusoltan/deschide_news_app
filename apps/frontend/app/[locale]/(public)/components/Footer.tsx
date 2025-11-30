import Link from 'next/link';

interface FooterProps {
  locale: string;
}

export default function Footer({ locale }: FooterProps) {
  return (
    <footer className="bg-black text-gray-400">
      {/* Footer content */}
      <div id="footer-content" className="relative pt-8 xl:pt-16 pb-6 xl:pb-12">
        <div className="xl:container mx-auto px-3 sm:px-4 xl:px-2 overflow-hidden">
          <div className="flex flex-wrap flex-row lg:justify-between -mx-3">
            {/* Left side - Brand and Social */}
            <div className="flex-shrink max-w-full w-full lg:w-2/5 px-3 lg:pr-16">
              <div className="flex items-center mb-2">
                <span className="text-3xl leading-normal mb-2 font-bold text-gray-100 mt-2">
                  Deschide News
                </span>
              </div>
              <p>Your trusted source for multilingual news and analysis.</p>
              <ul className="space-x-3 mt-6 mb-6 Lg:mb-0">
                {/* Facebook */}
                <li className="inline-block">
                  <a
                    target="_blank"
                    className="hover:text-gray-100"
                    rel="noopener noreferrer"
                    href="https://facebook.com"
                    title="Facebook"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="2rem"
                      height="2rem"
                      viewBox="0 0 512 512"
                    >
                      <path
                        fill="currentColor"
                        d="M455.27,32H56.73A24.74,24.74,0,0,0,32,56.73V455.27A24.74,24.74,0,0,0,56.73,480H256V304H202.45V240H256V189c0-57.86,40.13-89.36,91.82-89.36,24.73,0,51.33,1.86,57.51,2.68v60.43H364.15c-28.12,0-33.48,13.3-33.48,32.9V240h67l-8.75,64H330.67V480h124.6A24.74,24.74,0,0,0,480,455.27V56.73A24.74,24.74,0,0,0,455.27,32Z"
                      ></path>
                    </svg>
                  </a>
                </li>
                {/* Twitter */}
                <li className="inline-block">
                  <a
                    target="_blank"
                    className="hover:text-gray-100"
                    rel="noopener noreferrer"
                    href="https://twitter.com"
                    title="Twitter"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="2rem"
                      height="2rem"
                      viewBox="0 0 512 512"
                    >
                      <path
                        fill="currentColor"
                        d="M496,109.5a201.8,201.8,0,0,1-56.55,15.3,97.51,97.51,0,0,0,43.33-53.6,197.74,197.74,0,0,1-62.56,23.5A99.14,99.14,0,0,0,348.31,64c-54.42,0-98.46,43.4-98.46,96.9a93.21,93.21,0,0,0,2.54,22.1,280.7,280.7,0,0,1-203-101.3A95.69,95.69,0,0,0,36,130.4C36,164,53.53,193.7,80,211.1A97.5,97.5,0,0,1,35.22,199v1.2c0,47,34,86.1,79,95a100.76,100.76,0,0,1-25.94,3.4,94.38,94.38,0,0,1-18.51-1.8c12.51,38.5,48.92,66.5,92.05,67.3A199.59,199.59,0,0,1,39.5,405.6,203,203,0,0,1,16,404.2,278.68,278.68,0,0,0,166.74,448c181.36,0,280.44-147.7,280.44-275.8,0-4.2-.11-8.4-.31-12.5A198.48,198.48,0,0,0,496,109.5Z"
                      ></path>
                    </svg>
                  </a>
                </li>
                {/* YouTube */}
                <li className="inline-block">
                  <a
                    target="_blank"
                    className="hover:text-gray-100"
                    rel="noopener noreferrer"
                    href="https://youtube.com"
                    title="Youtube"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="2rem"
                      height="2rem"
                      viewBox="0 0 512 512"
                    >
                      <path
                        fill="currentColor"
                        d="M508.64,148.79c0-45-33.1-81.2-74-81.2C379.24,65,322.74,64,265,64H247c-57.6,0-114.2,1-169.6,3.6-40.8,0-73.9,36.4-73.9,81.4C1,184.59-.06,220.19,0,255.79q-.15,53.4,3.4,106.9c0,45,33.1,81.5,73.9,81.5,58.2,2.7,117.9,3.9,178.6,3.8q91.2.3,178.6-3.8c40.9,0,74-36.5,74-81.5,2.4-35.7,3.5-71.3,3.4-107Q512.24,202.29,508.64,148.79ZM207,353.89V157.39l145,98.2Z"
                      ></path>
                    </svg>
                  </a>
                </li>
                {/* Instagram */}
                <li className="inline-block">
                  <a
                    target="_blank"
                    className="hover:text-gray-100"
                    rel="noopener noreferrer"
                    href="https://instagram.com"
                    title="Instagram"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="2rem"
                      height="2rem"
                      viewBox="0 0 512 512"
                    >
                      <path
                        fill="currentColor"
                        d="M349.33,69.33a93.62,93.62,0,0,1,93.34,93.34V349.33a93.62,93.62,0,0,1-93.34,93.34H162.67a93.62,93.62,0,0,1-93.34-93.34V162.67a93.62,93.62,0,0,1,93.34-93.34H349.33m0-37.33H162.67C90.8,32,32,90.8,32,162.67V349.33C32,421.2,90.8,480,162.67,480H349.33C421.2,480,480,421.2,480,349.33V162.67C480,90.8,421.2,32,349.33,32Z"
                      ></path>
                      <path
                        fill="currentColor"
                        d="M377.33,162.67a28,28,0,1,1,28-28A27.94,27.94,0,0,1,377.33,162.67Z"
                      ></path>
                      <path
                        fill="currentColor"
                        d="M256,181.33A74.67,74.67,0,1,1,181.33,256,74.75,74.75,0,0,1,256,181.33M256,144A112,112,0,1,0,368,256,112,112,0,0,0,256,144Z"
                      ></path>
                    </svg>
                  </a>
                </li>
              </ul>
            </div>

            {/* Right side - Footer columns */}
            <div className="flex-shrink max-w-full w-full lg:w-3/5 px-3">
              <div className="flex flex-wrap flex-row">
                {/* Categories Column */}
                <div className="flex-shrink max-w-full w-1/2 md:w-1/4 mb-6 lg:mb-0">
                  <h4 className="text-base leading-normal mb-3 uppercase text-gray-100">
                    Categories
                  </h4>
                  <ul>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/category/politics`}>Politics</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/category/business`}>Business</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/category/technology`}>Technology</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/category/sports`}>Sports</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/category/entertainment`}>Entertainment</Link>
                    </li>
                  </ul>
                </div>

                {/* Quick Links Column */}
                <div className="flex-shrink max-w-full w-1/2 md:w-1/4 mb-6 lg:mb-0">
                  <h4 className="text-base leading-normal mb-3 uppercase text-gray-100">
                    Quick Links
                  </h4>
                  <ul>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}`}>Home</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/latest`}>Latest News</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/trending`}>Trending</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/archive`} className="flex items-center gap-1.5">
                        <svg className="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        {locale === 'ro' ? 'Arhivă' : locale === 'ru' ? 'Архив' : 'Archive'}
                      </Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/contact`}>Contact</Link>
                    </li>
                  </ul>
                </div>

                {/* About Us Column */}
                <div className="flex-shrink max-w-full w-1/2 md:w-1/4 mb-6 lg:mb-0">
                  <h4 className="text-base leading-normal mb-3 uppercase text-gray-100">
                    About Us
                  </h4>
                  <ul>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/about`}>Our Story</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/team`}>Our Team</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/careers`}>Careers</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/advertise`}>Advertise</Link>
                    </li>
                  </ul>
                </div>

                {/* Legal Column */}
                <div className="flex-shrink max-w-full w-1/2 md:w-1/4 mb-6 lg:mb-0">
                  <h4 className="text-base leading-normal mb-3 uppercase text-gray-100">
                    Legal
                  </h4>
                  <ul>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/privacy`}>Privacy Policy</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/terms`}>Terms of Use</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/license`}>License</Link>
                    </li>
                    <li className="py-1 hover:text-white">
                      <Link href={`/${locale}/gdpr`}>GDPR</Link>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer copyright */}
      <div className="footer-dark">
        <div className="container py-4 border-t border-gray-200 border-opacity-10">
          <div className="row">
            <div className="col-12 col-md text-center">
              <p className="d-block my-3">
                Copyright © {new Date().getFullYear()} Deschide News | All rights reserved.
              </p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
}
