<?php

namespace App\Http\Controllers;

use App\Advertising;
use App\Categories;
use App\Menu;
use App\News;
use App\ImageUploader;
use App\NewsImages;

//use App\Http\Controllers\Input;
use App\NewsTags;
use App\Tag;
use Illuminate\Support\Facades\Input;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MainController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ImageUploader;


    public function index()
    {
        $leftAdvertising = Advertising::query()->where('leftadvertising', '=', '1')->get();
        $rightAdvertising = Advertising::query()->where('leftadvertising', '=', '0')->get();

        $categories = \App\Categories::all();
        $lastNewsSlide = News::query()->orderBy('id', 'desc')->take(5)->get();

        $parentMenu = Menu::getMenu();
        return view('index', ['categories' => $categories, 'lastNewsSlide' => $lastNewsSlide, 'leftAdvertising' => $leftAdvertising, 'rightAdvertising' => $rightAdvertising, 'parentMenu' => $parentMenu]);
    }

    public function userNewsViewPage($id)
    {

        $images = News::find($id)->newsImg;
        $news = News::find($id);
        event('postHasViewed', $news);

        $preparedList = $news->getPreparedTagsList();

        return view('newsView', ['news' => $news, 'images' => $images, 'newsTag' => $preparedList]);
    }

    public function userCategoryViewPage($id)
    {
        $category = Categories::find($id);
        $newsByCategory = News::where('category_id', $id)->orderBy('id', 'desc')->paginate(5);

        return view('categoryView', ['newsByCategory' => $newsByCategory, 'category' => $category]);
    }

    // обзор всех статей по 1 тегу
    public function userTagPage($id)
    {
        $tag = Tag::find($id);
        $newsByTag = NewsTags::getNewsByTag($id);

        return view('tagView', ['tag' => $tag, 'newsByTag' => $newsByTag]);
    }

    //Администрирование
    public function adminPageView()
    {
        return view('admin.index');
    }


    // View управление категориями
    public function adminViewCategoryPage()
    {

        $categories = \App\Categories::all();

        return view('admin.category', ['categories' => $categories]);
    }

    // View page добавление новой категории
    public function adminAddCategoryView()
    {
        return view('admin.categoryAdd');
    }

    // Добавление новой категории
    public function adminActionAdminAddCategory()
    {
        $data = $_POST;
        Categories::create($data);
        return \redirect(route('addCategoryView'))->with('alert', 'Категория добавлена!');
    }

    // View редактирование категории
    public function adminViewUpdateCategory($id)
    {
        $category = Categories::find($id);
        return view('admin.categoryUpdate', ['category' => $category]);
    }

    // Action сохранить редактироование категории
    public function adminActionSaveUpdateCategory()
    {
        $data = $_POST;
        $categoryData = Categories::find($data['id']);
        $categoryData->update($data);
        return \redirect(route('viewCategoryAdmin'));
    }

    //Action удаление категории
    public function adminActionCategoryDelete($id)
    {
        $categoryDelete = Categories::find($id);
        $categoryDelete->delete();
        return \redirect(route('viewCategoryAdmin'));
    }

    //View новости
    public function adminViewNews()
    {
//        $newsCat = News::find($id)->category();
        // $newsCat = \App\News::find($id)->category();


        $news = \App\News::all();

        $category = Categories::getCategories();

        return view('admin.news', ['news' => $news, 'category' => $category]);
    }

    // View page добавления новой новости
    public function adminViewAddNews()
    {
        $preparedTags = Tag::getTagList();

        $categories = Categories::getCategories();
        return view('admin.newsAdd', ['categories' => $categories, 'tags' => $preparedTags]);
    }

    //Добавление новой новости
    public function adminActionAddNews(Request $request)
    {

        $path = '/news';  // Папка для загрузки картинок новости
        $fileName = self::uploader($request, $path);
        $data = Input::except(['_method', '_token']);

        $news = News::create($data);

        $news->tags()->attach($request->input('tagsPool'));
        $newsId = $news->id;

        if (!empty($fileName)) {
            foreach ($fileName as $oneFile) {
                $dataImages = ['filename' => $oneFile, 'news_id' => $newsId];

                NewsImages::create($dataImages);
            }
        }
        return \redirect(route('newsView'));
    }

    // Редактирование новости
    public function adminViewNewsUpdate($id)
    {
        $category = Categories::getCategories();
        $news = News::find($id);
        $images = News::find($id)->newsImg;

        $preparedTags = Tag::getTagList();

        event('postHasViewed', $news);
        $newsTagName = $news->getPreparedTagsList();

        $news_tagId = $news->getTagsAttribute();

        return view('admin.newsUpdate', ['news' => $news, 'category' => $category, 'images' => $images, 'preparedTags' => $preparedTags, 'newsTagName' => $newsTagName, 'news_tagId' => $news_tagId]);
    }

    // Action редактирование новости
    public function adminActionNewsUpdateSave(Request $request)
    {
        $path = '/news';  // Папка для загрузки картинок новости
        $fileName = self::uploader($request, $path);


        $data = Input::except(['_method', '_token', 'tagsPool']);
        $tags = Input::get('tagsPool');//array tags
        $newsData = News::find($data['id']);
        $newsData->update($data);

        $newsData->tags()->sync($tags);

        $newsId = $newsData->id;

        if ($fileName) {
            foreach ($fileName as $onefile) {
                $dataImages = ['filename' => $onefile, 'news_id' => $newsId];
                NewsImages::create($dataImages);
            }
        }

        return \redirect(route('newsView'));
    }


    // Action удаление  новости
    public function adminActionNewsDelete($id)
    {
        $newsDelete = News::find($id);
        $newsDelete->newsImg()->delete();
        $newsDelete->delete();

        return \redirect(route('newsView'));
    }

    // Получить картинку для слайда
    public static function getNewsMainImage($id)
    {
        $news = News::find($id);

        if (!$news) return false;
        $firstImage = $news->newsImg[0];
        return $firstImage->filename;
    }


    //Управление рекламой
    public function adminViewAdvertising()
    {
        $allAdvertising = Advertising::query()->orderBy('id', 'desc')->get();

        return view('admin.advertising', ['allAdvertising' => $allAdvertising]);
    }

    //View Добавление новой рекламы
    public function adminViewAddAdvertising()
    {

        return view('admin.advertisingAdd');
    }

    //Action Добавление новой рекламы
    public function adminActionAddAdvertising()
    {
        $data = $_POST;
        Advertising::create($data);

        return \redirect(route('viewAdvertising'));
    }

    //View редактирование рекламы
    public function adminViewAdvertisingUpdate($id)
    {
        $advertising = Advertising::find($id);

        return view('admin.advertisingUpdate', ['advertising' => $advertising]);
    }

    //Action редактирование рекламы
    public function adminActionAdvertisingUpdateSave()
    {
        $data = $_POST;
        $advertisingData = Advertising::find($data['id']);
        $advertisingData->update($data);
        return \redirect(route('viewAdvertising'));
    }

    //Action удаление рекламы
    public function adminActionAdvertisingDelete($id)
    {

        $advertisingDelete = Advertising::find($id);
        $advertisingDelete->delete();
        return \redirect(route('viewAdvertising'));
    }

    //View Управление тегами
    public function adminViewTag()
    {
        $tags = Tag::all();
        return view('admin.tags', ['tags' => $tags]);
    }

    // View page добавления нового тега
    public function adminViewAddTag()
    {
        return view('admin.tagsAdd');
    }

    // Action добавления нового тега
    public function adminActionAddNTag()
    {
        $data = $_POST;
        Tag::create($data);
        return \redirect(route('viewTag'));
    }

    //View редактирование тега
    public function adminViewUpdateTag($id)
    {
        $tag = Tag::find($id);
        return view('admin.tagsUpdate', ['tag' => $tag]);
    }

    //Action редактирование рекламы
    public function adminActionUpdateTag()
    {
        $data = $_POST;
        $tagData = Tag::find($data['id']);
        $tagData->update($data);
        return \redirect(route('viewTag'));
    }

    //Action удаление тега
    public function adminActionTagDelete($id)
    {
        $tag = Tag::find($id);
        $tag->delete();

        return \redirect(route('viewTag'));
    }

    //view page добавления нового пункта меню
    public function adminVievAddMenu()
    {
        $listMenu = Menu::pluck('name');
        return view('admin.menuAdd', ['listMenu' => $listMenu]);
    }

    //Action добавления нового пункта меню
    public function adminActionAddMenu()
    {
        $data = $_POST;
        Menu::create($data);


        return \redirect(route('viewAddMenu'));
    }

    // view  меню
    public function adminViewMenu()
    {
        $allMenu = Menu::all();
        return view('admin.menu', ['allMenu' => $allMenu]);
    }

    // view редактирование меню
    public function adminViewUpdateMenu($id)
    {
        $menu = Menu::find($id);

        $parents = Menu::parents();
        dd($parents);

        return view('admin.menuUpdate', ['menu' => $menu]);

    }

    //Action редактирование меню
    public function adminActionUpdateMenu()
    {

        return \redirect(route('viewMenu'));

    }
}