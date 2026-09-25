<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EpicWorldSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Latest', 'description' => 'The latest important developments and updates.', 'icon' => 'zap', 'sort_order' => 10],
            ['name' => 'Technology', 'description' => 'Technology, software, hardware, platforms and digital innovation.', 'icon' => 'cpu', 'sort_order' => 20],
            ['name' => 'AI', 'description' => 'Artificial intelligence, machine learning, models, agents and AI products.', 'icon' => 'sparkles', 'sort_order' => 30],
            ['name' => 'Business', 'description' => 'Companies, markets, leadership, strategy and the global business landscape.', 'icon' => 'briefcase', 'sort_order' => 40],
            ['name' => 'Startups', 'description' => 'Startups, founders, funding, products and emerging companies.', 'icon' => 'rocket', 'sort_order' => 50],
            ['name' => 'Careers', 'description' => 'Jobs, hiring, workplace trends, skills and career opportunities.', 'icon' => 'graduation-cap', 'sort_order' => 60],
            ['name' => 'Science', 'description' => 'Scientific discoveries, research, space, health science and exploration.', 'icon' => 'flask', 'sort_order' => 70],
            ['name' => 'Finance', 'description' => 'Markets, investing, banking, economics and financial technology.', 'icon' => 'chart', 'sort_order' => 80],
            ['name' => 'World', 'description' => 'Important international developments and global affairs.', 'icon' => 'globe', 'sort_order' => 90],
            ['name' => 'India', 'description' => 'Important developments, technology, business and society across India.', 'icon' => 'map', 'sort_order' => 100],
            ['name' => 'Education', 'description' => 'Education, learning, universities, courses and skills.', 'icon' => 'book-open', 'sort_order' => 110],
            ['name' => 'Cybersecurity', 'description' => 'Cybersecurity, privacy, threats, vulnerabilities and digital safety.', 'icon' => 'shield', 'sort_order' => 120],
            ['name' => 'Lifestyle', 'description' => 'Lifestyle, culture, productivity, travel and modern living.', 'icon' => 'heart', 'sort_order' => 130],
            ['name' => 'Explainers', 'description' => 'Clear explanations that provide context behind important topics.', 'icon' => 'info', 'sort_order' => 140],
            ['name' => 'Trending', 'description' => 'Topics receiving significant attention and discussion.', 'icon' => 'trending-up', 'sort_order' => 150],
        ];

        $topicMap = [
            'technology' => ['Software', 'Hardware', 'Gadgets', 'Cloud', 'Developer Tools', 'Platforms'],
            'ai' => ['AI Models', 'AI Agents', 'Generative AI', 'Robotics', 'AI Products', 'AI Research'],
            'business' => ['Companies', 'Markets', 'Leadership', 'Corporate', 'Industry'],
            'startups' => ['Funding', 'Founders', 'Products', 'Venture Capital', 'Startup Ecosystem'],
            'careers' => ['Jobs', 'Hiring', 'Skills', 'Remote Work', 'Workplace'],
            'science' => ['Space', 'Research', 'Physics', 'Biology', 'Climate'],
            'finance' => ['Stocks', 'Banking', 'Economy', 'Fintech', 'Personal Finance'],
            'world' => ['Global Affairs', 'Technology', 'Business', 'Science', 'Society'],
            'india' => ['Technology', 'Business', 'Startups', 'Economy', 'Society'],
            'education' => ['Universities', 'Courses', 'Skills', 'Scholarships', 'EdTech'],
            'cybersecurity' => ['Threats', 'Vulnerabilities', 'Privacy', 'Cloud Security', 'Security Research'],
            'lifestyle' => ['Productivity', 'Culture', 'Travel', 'Digital Life'],
            'explainers' => ['How It Works', 'Deep Dives', 'Background', 'Guides'],
        ];

        foreach ($categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'icon' => $categoryData['icon'],
                    'is_active' => true,
                    'sort_order' => $categoryData['sort_order'],
                ]
            );

            foreach ($topicMap[$category->slug] ?? [] as $topicName) {
                Topic::updateOrCreate(
                    ['slug' => Str::slug($topicName)],
                    [
                        'category_id' => $category->id,
                        'name' => $topicName,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}